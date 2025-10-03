<?php

namespace PhpXmlRpc\JsonRpc\Helper;

use PhpXmlRpc\Exception\StateErrorException;
use PhpXmlRpc\JsonRpc\Encoder;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Value;

/**
 * @todo implement a CharsetEncoderAware trait (as soon as there is a 2nd user of Charset)
 */
class Serializer
{
    protected static $charsetEncoder;

    public static $defaultJsonrpcVersion = PhpJsonRpc::VERSION_2_0;

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
     * @return string
     */
    public function serializeValue($value, $charsetEncoding = '')
    {
        $val = $value->scalarVal();
        $typ = $value->scalarTyp();

        $rs = '';
        switch (@Value::$xmlrpcTypes[$typ]) {
            case 1:
                switch ($typ) {
                    case Value::$xmlrpcString:
                        $rs .= '"' . $this->getCharsetEncoder()->encodeEntities($val, null, $charsetEncoding) . '"';
                        break;
                    case Value::$xmlrpcInt:
                    case Value::$xmlrpcI4:
                    case Value::$xmlrpcI8;
                        $rs .= (int)$val;
                        break;
                    case Value::$xmlrpcDateTime:
                        // quote date as a json string.
                        // assumes date format is valid and will not break js...
/// @todo check: how to handle the cases where $val is a timestamp or a DateTime?
                        $rs .= '"' . $val . '"';
                        break;
                    case Value::$xmlrpcDouble:
                        // add a .0 in case value is integer.
                        // This helps us to carry around floats in js, and keep them separated from ints
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
                /// @todo implement json-rpc extension for object serialization
                //if ($value->_php_class)
                //{
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
     * @return string
     */
    public function serializeRequest($req, $id = null, $charsetEncoding = '')
    {
        // @todo: verify if all chars are allowed for method names or can we just skip the js encoding on it?
        $result = "{\n\"method\": \"" . $this->getCharsetEncoder()->encodeEntities($req->method(), '', $charsetEncoding) . "\",\n";

        if (is_callable([$req, 'getJsonRpcVersion'])) {
            $jsonRpcVersion = $req->getJsonRpcVersion();
        } else {
            $jsonRpcVersion = self::$defaultJsonrpcVersion;
        }

        $useNamedParameters = false;
        if ($jsonRpcVersion == PhpJsonRpc::VERSION_2_0 && is_callable([$req, 'getParamNames'])) {
            $useNamedParameters = true;
            $paramNames = $req->getParamNames();
            foreach($paramNames as $paramName) {
                if (!is_string($paramName)) {
                    $useNamedParameters = false;
                    break;
                }
            }
        }

        if ($useNamedParameters) {
            $result .= "\"params\": {";
            for ($i = 0; $i < $req->getNumParams(); $i++) {
                $p = $req->getParam($i);
                // NB: we try to force serialization as json even though the object param might be a plain xmlrpcval object.
                // This way we do not need to override addParam, aren't we lazy?
                $result .= "\n  \"" . $this->getCharsetEncoder()->encodeEntities($paramNames[$i], null, $charsetEncoding) . "\": " . $this->serializeValue($p, $charsetEncoding) . ",";
            }
            $result = substr($result, 0, -1) . "\n},\n";
        } else {
            $result .= "\"params\": [";
            for ($i = 0; $i < $req->getNumParams(); $i++) {
                $p = $req->getParam($i);
                // NB: we try to force serialization as json even though the object param might be a plain xmlrpcval object.
                // This way we do not need to override addParam, aren't we lazy?
                $result .= "\n  " . $this->serializeValue($p, $charsetEncoding) . ",";
            }
            $result = substr($result, 0, -1) . "\n],\n";
        }

        // In jsonrpc 2.0 null ids are omitted. In 1.0, they are not
        if ($id !== null || $jsonRpcVersion != PhpJsonRpc::VERSION_2_0) {
            $result .= "\"id\": ";
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
                    // integer
                    /// @todo handle specially: object, resource
                    $result .= $id;
            }
        }

        switch ($jsonRpcVersion) {
            case PhpJsonRpc::VERSION_1_0:
                $result .= "\n";
                break;
            case PhpJsonRpc::VERSION_2_0:
                $result .= ",\n\"jsonrpc\": \"2.0\"\n";
                break;
            default:
                /// @todo throw
                break;
        }

        $result .= "}\n";

        return $result;
    }

    /**
     * Serialize a Response as json.
     * Moved outside the corresponding class to ease multi-serialization of xmlrpc response objects.
     *
     * @param \PhpXmlRpc\Response $resp
     * @param mixed $id
     * @param string $charsetEncoding
     * @return string
     * @throws \Exception
     */
    public function serializeResponse($resp, $id = null, $charsetEncoding = '')
    {
        if (is_callable([$resp, 'getJsonRpcVersion'])) {
            $jsonRpcVersion = $resp->getJsonRpcVersion();
        } else {
            $jsonRpcVersion = self::$defaultJsonrpcVersion;
        }

        $result = "{\n";

        // NB: NULL id has different meaning in jsonrpc 1.0 vs 2.0
        $result .= "\"id\": ";
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
        $result .= ",\n";

        if ($resp->faultCode()) {
            // let non-ASCII response messages be tolerated by clients by encoding non ascii chars
            if ($jsonRpcVersion == PhpJsonRpc::VERSION_2_0) {
                $result .= "\"error\": { \"code\": " . $resp->faultCode() . ", \"message\": \"" . $this->getCharsetEncoder()->encodeEntities($resp->errstr, null, $charsetEncoding) . "\" }";
            } else {
                $result .= "\"error\": { \"faultCode\": " . $resp->faultCode() . ", \"faultString\": \"" . $this->getCharsetEncoder()->encodeEntities($resp->errstr, null, $charsetEncoding) . "\" },";
                $result .= "\"result\": null";
            }
        } else {
            $result .= "\"result\": ";
            $val = $resp->value();
            if (is_object($val) && is_a($val, 'PhpXmlRpc\Value')) {
                $result .= $this->serializeValue($val, $charsetEncoding);
            } else if (is_string($val) && $resp->valueType() == 'json') {
                $result .= $val;
            } else if ($resp->valueType() == 'phpvals') {
                $encoder = new Encoder();
                $val = $encoder->encode($val);
                $result .= $val->serialize($charsetEncoding);
            } else {
                throw new StateErrorException('cannot serialize jsonrpcresp objects whose content is native php values');
            }
            if ($jsonRpcVersion != PhpJsonRpc::VERSION_2_0) {
                $result .= ",\n\"error\": null";
            }
        }

        switch ($jsonRpcVersion) {
            case PhpJsonRpc::VERSION_1_0:
                $result .= "\n";
                break;
            case PhpJsonRpc::VERSION_2_0:
                $result .= ",\n\"jsonrpc\": \"2.0\"\n";
                break;
            default:
                /// @todo throw
                break;
        }

        $result .= "}";

        return $result;
    }
}
