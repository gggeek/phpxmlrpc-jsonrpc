<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\Value;

/**
 * A helper class to easily convert between Value objects and php native values
 * @todo implement an interface
 * @todo add class constants for the options values
 */
class Encoder
{
    protected static $serializer;

    public function getSerializer()
    {
        if (self::$serializer === null) {
            self::$serializer = new Serializer();
        }
        return self::$serializer;
    }

    public static function setSerializer($serializer)
    {
        self::$serializer = $serializer;
    }

    /**
     * Takes a jsonrpc value in object format and translates it into native PHP types.
     *
     * Works with xmlrpc objects as input, too.
     *
     * @param Value|Request $jsonrpcVal
     * @param array $options if 'decode_php_objs' is set in the options array, jsonrpc objects can be decoded into php objects
     * @return mixed
     *
     * @todo add support for Request objects
     */
    public function decode($jsonrpcVal, $options = array())
    {
        switch ($jsonrpcVal->kindOf()) {
            case 'scalar':
                /// @todo should we support 'dates_as_objects' and datetime xmlrpc Values ?
                return $jsonrpcVal->scalarval();

            case 'array':
                $arr = array();
                foreach($jsonrpcVal as $value) {
                    $arr[] = $this->decode($value, $options);
                }
                return $arr;

            case 'struct':
                // If user said so, try to rebuild php objects for specific struct vals.
                /// @todo should we raise a warning for class not found?
                // shall we check for proper subclass of xmlrpc value instead of presence of _php_class to detect
                // what we can do?
                if (in_array('decode_php_objs', $options) && $jsonrpcVal->_php_class != ''
                    && class_exists($jsonrpcVal->_php_class)) {
                    $obj = @new $jsonrpcVal->_php_class();
                    foreach ($jsonrpcVal as $key => $value) {
                        $obj->$key = $this->decode($value, $options);
                    }

                    return $obj;
                } else {
                    $arr = array();
                    foreach ($jsonrpcVal as $key => $value) {
                        $arr[$key] = $this->decode($value, $options);
                    }
                    return $arr;
                }

            case 'msg':
                $paramCount = $jsonrpcVal->getNumParams();
                $arr = array();
                for ($i = 0; $i < $paramCount; $i++) {
                    $arr[] = $this->decode($jsonrpcVal->getParam($i), $options);
                }
                return $arr;

            /// @todo throw on unsupported type
        }
    }

    /**
     * Takes native php types and encodes them into jsonrpc PHP object format.
     * It will not re-encode Value objects.
     *
     * @param mixed $phpVal the value to be converted into a Value object
     * @param array $options can include 'encode_php_objs', 'auto_dates' (which means php DateTimes will be encoded as iso datetime strings)
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
                // PHP arrays can be encoded to either objects or arrays, depending on whether they are hashes or plain
                // 0..n integer indexed
                // A shorter one-liner would be
                //   $tmp = array_diff(array_keys($phpVal), range(0, count($phpVal)-1));
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
                if (is_a($phpVal, 'PhpXmlrpc\Value')) {
                    $jsonrpcVal = $phpVal;
                } else if (is_a($phpVal, 'DateTimeInterface') && in_array('auto_dates', $options)) {
                    $jsonrpcVal = new Value($phpVal->format('Ymd\TH:i:s'), Value::$xmlrpcDateTime);
                } else {
                    $arr = array();
                    foreach($phpVal as $k => $v) {
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
}
