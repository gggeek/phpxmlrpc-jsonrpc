<?php

namespace PhpXmlRpc\JsonRpc\Helper;

use PhpXmlRpc\JsonRpc\Value;

/**
 * @todo once we make php 5.4 a mandatory requirement, implement a CharsetEncoderAware trait
 */
class Serializer
{
    protected static $charsetEncoder;

    public function getCharsetEncoder()
    {
        if (self::$charsetEncoder === null) {
            self::$charsetEncoder = Charset::instance();
        }
        return self::$charsetEncoder;
    }

    public function setCharsetEncoder($charsetEncoder)
    {
        self::$charsetEncoder = $charsetEncoder;
    }

    /**
     * Serialize a json-rpc Value (or xml-rpc Value) as json.
     * Moved outside the corresponding class to ease multi-serialization of xml-rpc value objects
     *
     * @param \PhpXmlRpc\Value $value
     * @param string $charsetEncoding
     *
     * @return string
     */
    public function serializeValue($value, $charsetEncoding = '')
    {
        $val = reset($value->me);
        $typ = key($value->me);

        $rs = '';
        switch (@Value::$xmlrpcTypes[$typ]) {
            case 1:
                switch ($typ) {
                    case Value::$xmlrpcString:
                        $rs .= '"' . $this->getCharsetEncoder()->encodeEntities($val, null, $charsetEncoding) . '"';
                        break;
                    case Value::$xmlrpcI4:
                    case Value::$xmlrpcInt:
                        $rs .= (int)$val;
                        break;
                    case Value::$xmlrpcDateTime:
                        // quote date as a json string.
                        // assumes date format is valid and will not break js...
                        $rs .= '"' . $val . '"';
                        break;
                    case Value::$xmlrpcDouble:
                        // add a .0 in case value is integer.
                        // This helps us carrying around floats in js, and keep them separated from ints
                        $sval = strval((double)$val); // convert to string
                        // fix usage of comma, in case of eg. german locale
                        $sval = str_replace(',', '.', $sval);
                        if (strpos($sval, '.') !== false || strpos($sval, 'e') !== false) {
                            $rs .= $sval;
                        } else {
                            $rs .= $val . '.0';
                        }
                        break;
                    case Value::$xmlrpcBoolean:
                        $rs .= ($val ? 'true' : 'false');
                        break;
                    case Value::$xmlrpcBase64:
                        // treat base 64 values as strings ???
                        $rs .= '"' . base64_encode($val) . '"';
                        break;
                    default:
                        $rs .= "null";
                }
                break;
            case 2:
                // array
                $rs .= "[";
                $len = sizeof($val);
                if ($len) {
                    for ($i = 0; $i < $len; $i++) {
                        $rs .= $this->serializeValue($val[$i], $charsetEncoding);
                        $rs .= ",";
                    }
                    $rs = substr($rs, 0, -1) . "]";
                } else {
                    $rs .= "]";
                }
                break;
            case 3:
                // struct
                //if ($value->_php_class)
                //{
                /// @todo implement json-rpc extension for object serialization
                //$rs.='<struct php_class="' . $this->_php_class . "\">\n";
                //}
                //else
                //{
                //}
                foreach ($val as $key2 => $val2) {
                    $rs .= ',"' . $this->getCharsetEncoder()->encodeEntities($key2, null, $charsetEncoding) . '":';
                    $rs .= $this->serializeValue($val2, $charsetEncoding);
                }
                $rs = '{' . substr($rs, 1) . '}';
                break;
            case 0:
                // let uninitialized jsonrpcval objects serialize to an empty string, as they do in xml-rpc land
                $rs = '""';
                break;
            default:
                break;
        }
        return $rs;
    }

    /**
     * @param \PhpXmlRpc\Request $req
     * @param mixed $id
     * @param string $charsetEncoding
     *
     * @return string
     */
    public function serializeRequest($req, $id = null, $charsetEncoding = '')
    {
        // @ todo: verify if all chars are allowed for method names or can
        // we just skip the js encoding on it?
        $result = "{\n\"method\": \"" . $this->getCharsetEncoder()->encodeEntities($req->methodname, '', $charsetEncoding) . "\",\n\"params\": [ ";
        for ($i = 0; $i < sizeof($req->params); $i++) {
            $p = $req->params[$i];
            // NB: we try to force serialization as json even though the object
            // param might be a plain xmlrpcval object.
            // This way we do not need to override addParam, aren't we lazy?
            $result .= "\n  " . $this->serializeValue($p, $charsetEncoding) .
                ",";
        }
        $result = substr($result, 0, -1) . "\n],\n\"id\": ";
        switch (true) {
            case $id === null:
                $result .= 'null';
                break;
            case is_string($id):
                $result .= '"' . $this->getCharsetEncoder()->encodeEntities($id, '', $charsetEncoding) . '"';
                break;
            case is_bool($id):
                $result .= ($id ? 'true' : 'false');
                break;
            default:
                $result .= $id;
        }
        $result .= "\n}\n";

        return $result;
    }

    /**
     * Serialize a Response as json.
     * Moved outside of the corresponding class to ease multi-serialization of xmlrpc response objects
     * @param \PhpXmlRpc\Response $resp
     * @param mixed $id
     * @param string $charsetEncoding
     *
     * @return string
     *
     * @throws \Exception
     */
    public function serializeResponse($resp, $id = null, $charsetEncoding = '')
    {
        $result = "{\n\"id\": ";
        switch (true) {
            case $id === null:
                $result .= 'null';
                break;
            case is_string($id):
                $result .= '"' . $this->getCharsetEncoder()->encodeEntities($id, '', $charsetEncoding) . '"';
                break;
            case is_bool($id):
                $result .= ($id ? 'true' : 'false');
                break;
            default:
                $result .= $id;
        }
        $result .= ", ";
        if ($resp->faultCode()) {
            // let non-ASCII response messages be tolerated by clients
            // by encoding non ascii chars
            $result .= "\"error\": { \"faultCode\": " . $resp->faultCode() . ", \"faultString\": \"" . $this->getCharsetEncoder()->encodeEntities($resp->errstr, null, $charsetEncoding) . "\" }, \"result\": null";
        } else {
            $val= $resp->value();
            if (!is_object($val) || !is_a($val, 'PhpXmlRpc\Value')) {
                if (is_string($val) && $resp->valtyp == 'json') {
                    $result .= "\"error\": null, \"result\": " . $val;
                } else {
                    /// @todo try to build something serializable?
                    throw new \Exception('cannot serialize jsonrpcresp objects whose content is native php values');
                }
            } else {
                $result .= "\"error\": null, \"result\": " .
                    $this->serializeValue($val, $charsetEncoding);
            }
        }
        $result .= "\n}";

        return $result;
    }
}
