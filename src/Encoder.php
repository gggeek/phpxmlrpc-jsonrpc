<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Value;

/**
 * A helper class to easily convert between Value objects and php native values
 * @todo implement an interface
 * @todo add class constants for the options values
 */
class Encoder
{
    /**
     * Takes a jsonrpc value in object format and translates it into native PHP types.
     *
     * Works with xmlrpc objects as input, too.
     *
     * @param Value $jsonrpcVal
     * @param array $options if 'decode_php_objs' is set in the options array, jsonrpc objects can be decoded into php objects
     * @return mixed
     *
     * @todo add support for Request objects
     */
    public function decode($jsonrpcVal, $options = array())
    {
        $kind = $jsonrpcVal->kindOf();

        if ($kind == 'scalar') {
            return $jsonrpcVal->scalarval();
        } elseif ($kind == 'array') {
/// @todo
            $size = $jsonrpcVal->arraysize();
            $arr = array();

            for ($i = 0; $i < $size; $i++) {
                $arr[] = $this->decode($jsonrpcVal->arraymem($i), $options);
            }
            return $arr;
        } elseif ($kind == 'struct') {
/// @todo
            $jsonrpcVal->structreset();
            // If user said so, try to rebuild php objects for specific struct vals.
            /// @todo should we raise a warning for class not found?
            // shall we check for proper subclass of xmlrpcval instead of
            // presence of _php_class to detect what we can do?
            if (in_array('decode_php_objs', $options)) {
                if ($jsonrpcVal->_php_class != '' && class_exists($jsonrpcVal->_php_class)
                ) {
                    $obj = @new $jsonrpcVal->_php_class;
                } else {
                    $obj = new \stdClass();
                }
                while (list($key, $value) = $jsonrpcVal->structeach()) {
                    $obj->$key = $this->decode($value, $options);
                }
                return $obj;
            } else {
/// @todo
                $arr = array();
                while (list($key, $value) = $jsonrpcVal->structeach()) {
                    $arr[$key] = $this->decode($value, $options);
                }
                return $arr;
            }
        }
    }

    /**
     * Takes native php types and encodes them into jsonrpc PHP object format.
     * It will not re-encode Value objects.
     *
     * @param mixed $phpVal the value to be converted into a Value object
     * @param array $options can include 'encode_php_objs'
     * @return Value
     */
    public function encode($phpVal, $options = array())
    {
        $type = gettype($phpVal);

        switch ($type) {
            case 'string':
                $jsonrpcVal = new Value($phpVal, Value::$xmlrpcString);
                break;
            case 'integer':
                $jsonrpcVal = new Value($phpVal, Value::$xmlrpcInt);
                break;
            case 'double':
                $jsonrpcVal = new Value($phpVal, Value::$xmlrpcDouble);
                break;
            case 'boolean':
                $jsonrpcVal = new Value($phpVal, Value::$xmlrpcBoolean);
                break;
            case 'resource': // for compat with php json extension...
            case 'NULL':
                $jsonrpcVal = new Value($phpVal, Value::$xmlrpcNull);
                break;
            case 'array':
                // PHP arrays can be encoded to either objects or arrays,
                // depending on whether they are hashes or plain 0..n integer indexed
                // A shorter one-liner would be
                // $tmp = array_diff(array_keys($phpVal), range(0, count($phpVal)-1));
                // but execution time skyrockets!
                $j = 0;
                $arr = array();
                $ko = false;
                foreach ($phpVal as $key => $val) {
                    $arr[$key] = $this->encode($val, $options);
                    if (!$ko && $key !== $j) {
                        $ko = true;
                    }
                    $j++;
                }
                if ($ko) {
                    $jsonrpcVal = new Value($arr, Value::$xmlrpcStruct);
                } else {
                    $jsonrpcVal = new Value($arr, Value::$xmlrpcArray);
                }
                break;
            case 'object':
/// @todo
                if (is_a($phpVal, 'Value')) {
                    $jsonrpcVal = $phpVal;
                } else {
                    $arr = array();
                    reset($phpVal);
/// @todo
                    while (list($k, $v) = each($phpVal)) {
                        $arr[$k] = $this->encode($v, $options);
                    }
                    $jsonrpcVal = new Value($arr, Value::$xmlrpcStruct);
                    if (in_array('encode_php_objs', $options)) {
                        // let's save original class name into xmlrpcval:
                        // might be useful later on...
                        $jsonrpcVal->_php_class = get_class($phpVal);
                    }
                }
                break;
            // catch "user function", "unknown type"
            default:
                $jsonrpcVal = new Value();
                break;
        }

        return $jsonrpcVal;
    }

    /**
     * Convert the json representation of a jsonrpc method call, jsonrpc method response
     * or single json value into the appropriate object (a.k.a. deserialize).
     * Please note that there is no way to distinguish the serialized representation
     * of a single json val of type object which has the 3 appropriate members from
     * the serialization of a method call or method response.
     * In such a case, the function will return a jsonrpcresp or jsonrpcmsg
     * @param string $jsonVal
     * @param array $options includes src_encoding, dest_encoding
     * @return mixed false on error, or an instance of jsonrpcval, jsonrpcresp or jsonrpcmsg
     */
    public function decodeJson($jsonVal, $options = array())
    {
        $src_encoding = array_key_exists('src_encoding', $options) ? $options['src_encoding'] : PhpXmlRpc::$xmlrpc_defencoding;
        $dest_encoding = array_key_exists('dest_encoding', $options) ? $options['dest_encoding'] : PhpXmlRpc::$xmlrpc_internalencoding;

        //$GLOBALS['_xh'] = array();
        $GLOBALS['_xh']['isf'] = 0;
        if (!json_parse($jsonVal, false, $src_encoding, $dest_encoding)) {
            error_log($GLOBALS['_xh']['isf_reason']);
            return false;
        } else {
            $val = $GLOBALS['_xh']['value']; // shortcut
            if ($GLOBALS['_xh']['value']->kindOf() == 'struct') {
                if ($GLOBALS['_xh']['value']->structSize() == 3) {
                    if ($GLOBALS['_xh']['value']->structMemExists('method') &&
                        $GLOBALS['_xh']['value']->structMemExists('params') &&
                        $GLOBALS['_xh']['value']->structMemExists('id')
                    ) {
                        /// @todo we do not check for correct type of 'method', 'params' struct members...
                        $method = $GLOBALS['_xh']['value']->structMem('method');
                        $msg = new Request($method->scalarval(), null, $this->decode($GLOBALS['_xh']['value']->structMem('id')));
                        $params = $GLOBALS['_xh']['value']->structMem('params');
                        for ($i = 0; $i < $params->arraySize(); ++$i) {
                            $msg->addparam($params->arrayMem($i));
                        }
                        return $msg;
                    } else
                        if ($GLOBALS['_xh']['value']->structMemExists('result') &&
                            $GLOBALS['_xh']['value']->structMemExists('error') &&
                            $GLOBALS['_xh']['value']->structMemExists('id')
                        ) {
                            $id = $this->decode($GLOBALS['_xh']['value']->structMem('id'));
                            $err = $this->decode($GLOBALS['_xh']['value']->structMem('error'));
                            if ($err == null) {
                                $resp = new Response($GLOBALS['_xh']['value']->structMem('result'));
                            } else {
                                if (is_array($err) && array_key_exists('faultCode', $err)
                                    && array_key_exists('faultString', $err)
                                ) {
                                    if ($err['faultCode'] == 0) {
                                        // FAULT returned, errno needs to reflect that
                                        $err['faultCode'] = -1;
                                    }
                                }
                                // NB: what about jsonrpc servers that do NOT respect
                                // the faultCode/faultString convention???
                                // we force the error into a string. regardless of type...
                                else //if (is_string($GLOBALS['_xh']['value']))
                                {
                                    $err = array('faultCode' => -1, 'faultString' => serialize_jsonrpcval($GLOBALS['_xh']['value']->structMem('error')));
                                }
                                $resp = new Response(0, $err['faultCode'], $err['faultString']);
                            }
                            $resp->id = $id;
                            return $resp;
                        }
                }
            }
            // not a request msg nor a response: a plain jsonrpcval obj
            return $GLOBALS['_xh']['value'];
        }
    }
}
