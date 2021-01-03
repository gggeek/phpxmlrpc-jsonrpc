<?php


namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Value as BaseValue;

class Value extends BaseValue
{
    /**
     * Returns json representation of the value.
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string
     * @access public
     */
    function serialize($charsetEncoding = '')
    {
        return serialize_jsonrpcval($this, $charsetEncoding);
    }
}
