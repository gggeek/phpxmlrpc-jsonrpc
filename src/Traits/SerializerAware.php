<?php

namespace PhpXmlRpc\JsonRpc\Traits;

use PhpXmlRpc\JsonRpc\Helper\Serializer;

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
