<?php

namespace PhpXmlRpc\JsonRpc\Traits;

use PhpXmlRpc\JsonRpc\Helper\Serializer;

/**
 * NB: if a class implements this trait, and it is subclassed, instances of the class and of the subclass will share
 * the same serializer instance, unless the subclass reimplements these methods
 */
trait SerializerAware
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
}
