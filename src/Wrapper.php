<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Value;

class Wrapper
{
    /**
     * Given a string defining a php type or phpxmlrpc type (loosely defined: strings
     * accepted come from javadoc blocks), return corresponding phpxmlrpc type.
     * NB: for php 'resource' types returns empty string, since resources cannot be serialized;
     * for php class names returns 'struct', since php objects can be serialized as json structs;
     * for php arrays always return 'array', even though arrays sometimes serialize as json structs
     * @param string $phpType
     * @return string
     */
    public function php2JsonrpcType($phpType)
    {
        switch (strtolower($phpType)) {
            case 'string':
                return Value::$xmlrpcString;
            case 'integer':
            case Value::$xmlrpcInt: // 'int'
            case Value::$xmlrpcI4:
                return Value::$xmlrpcInt;
            case 'double':
                return Value::$xmlrpcDouble;
            case 'boolean':
                return Value::$xmlrpcBoolean;
            case 'array':
                return Value::$xmlrpcArray;
            case 'object':
                return Value::$xmlrpcStruct;
            //case Value::$xmlrpcBase64:
            case Value::$xmlrpcStruct:
                return strtolower($phpType);
            case 'resource':
                return '';
            default:
                if (class_exists($phpType)) {
                    return Value::$xmlrpcStruct;
                } else {
                    // unknown: might be any 'extended' jsonrpc type
                    return Value::$xmlrpcValue;
                }
        }
    }
}
