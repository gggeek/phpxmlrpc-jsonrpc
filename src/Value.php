<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\Value as BaseValue;

/**
 * @todo once we make php 5.4 a mandatory requirement, implement a SerializerAware trait
 */
class Value extends BaseValue
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
     * Returns json representation of the value.
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string
     */
    public function serialize($charsetEncoding = '')
    {
        return $this->getSerializer()->serializeValue($this, $charsetEncoding);
    }
}
