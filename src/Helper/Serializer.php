<?php

namespace PhpXmlRpc\JsonRpc\Helper;

class Serializer
{
    /**
     * Serialize a Response as json.
     * Moved outside of the corresponding class to ease multi-serialization of xmlrpc response objects
     * @param \PhpXmlRpc\Response $resp
     * @param mixed $id
     * @param string $charsetEncoding
     * @return string
     */
    public function serializeResponse($resp, $id = null, $charsetEncoding = '')
    {
        $result = "{\n\"id\": ";
        switch (true) {
            case $id === null:
                $result .= 'null';
                break;
            case is_string($id):
                $result .= '"' . Charset::instance()->encodeEntities($id, '', $charsetEncoding) . '"';
                break;
            case is_bool($id):
                $result .= ($id ? 'true' : 'false');
                break;
            default:
                $result .= $id;
        }
        $result .= ", ";
        if ($resp->errno) {
            // let non-ASCII response messages be tolerated by clients
            // by encoding non ascii chars
            $result .= "\"error\": { \"faultCode\": " . $resp->errno . ", \"faultString\": \"" . Charset::instance()->encodeEntities($resp->errstr, null, $charsetEncoding) . "\" }, \"result\": null";
        } else {
            if (!is_object($resp->val) || !is_a($resp->val, 'xmlrpcval')) {
                if (is_string($resp->val) && $resp->valtyp == 'json') {
                    $result .= "\"error\": null, \"result\": " . $resp->val;
                } else {
                    /// @todo try to build something serializable?
                    die('cannot serialize jsonrpcresp objects whose content is native php values');
                }
            } else {
                $result .= "\"error\": null, \"result\": " .
                    serialize_jsonrpcval($resp->val, $charsetEncoding);
            }
        }
        $result .= "\n}";
        return $result;
    }

    /**
     * Serialize a jsonrpcval (or xmlrpcval) as json.
     * Moved outside of the corresponding class to ease multi-serialization of xmlrpc value objects
     * @param \PhpXmlRpc\Value $value
     * @param string $charsetEncoding
     * @return string
     */
    public function serializeValue($value, $charsetEncoding = '')
    {
        reset($value->me);
        list($typ, $val) = each($value->me);

        $rs = '';
        switch (@$GLOBALS['xmlrpcTypes'][$typ]) {
            case 1:
                switch ($typ) {
                    case $GLOBALS['xmlrpcString']:
                        $rs .= '"' . Charset::instance()->encodeEntities($val, null, $charsetEncoding) . '"';
                        break;
                    case $GLOBALS['xmlrpcI4']:
                    case $GLOBALS['xmlrpcInt']:
                        $rs .= (int)$val;
                        break;
                    case $GLOBALS['xmlrpcDateTime']:
                        // quote date as a json string.
                        // assumes date format is valid and will not break js...
                        $rs .= '"' . $val . '"';
                        break;
                    case $GLOBALS['xmlrpcDouble']:
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
                    case $GLOBALS['xmlrpcBoolean']:
                        $rs .= ($val ? 'true' : 'false');
                        break;
                    case $GLOBALS['xmlrpcBase64']:
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
                        $rs .= serialize_jsonrpcval($val[$i], $charsetEncoding);
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
                    $rs .= ',"' . Charset::instance()->encodeEntities($key2, null, $charsetEncoding) . '":';
                    $rs .= serialize_jsonrpcval($val2, $charsetEncoding);
                }
                $rs = '{' . substr($rs, 1) . '}';
                break;
            case 0:
                // let uninitialized jsonrpcval objects serialize to an empty string, as they do in xmlrpc land
                $rs = '""';
                break;
            default:
                break;
        }
        return $rs;
    }
}
