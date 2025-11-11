<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\Value as BaseValue;

class Value extends BaseValue
{
    use SerializerAware;

    protected static $jsonRpcCharsetEncoder;

    /**
     * Reimplemented to make us use the correct charset encoder type.
     *
     * @return Charset
     */
    public function getCharsetEncoder()
    {
        if (self::$jsonRpcCharsetEncoder === null) {
            self::$jsonRpcCharsetEncoder = Charset::instance();
        }
        return self::$jsonRpcCharsetEncoder;
    }

    public static function setCharsetEncoder($charsetEncoder)
    {
        self::$jsonRpcCharsetEncoder = $charsetEncoder;
    }

    /**
     * Returns json representation of the value.
     *
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string
     */
    public function serialize($charsetEncoding = '')
    {
        return $this->getSerializer()->serializeValue($this, $charsetEncoding);
    }
}
