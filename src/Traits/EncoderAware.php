<?php

namespace PhpXmlRpc\JsonRpc\Traits;

use PhpXmlRpc\JsonRpc\Encoder;

/**
 * NB: if a class implements this trait, and it is subclassed, instances of the class and of the subclass will share
 * the same encoder instance, unless the subclass reimplements these methods
 */
trait EncoderAware
{
    protected static $encoder;

    public function getEncoder()
    {
        if (self::$encoder === null) {
            self::$encoder = new Encoder();
        }
        return self::$encoder;
    }

    public static function setEncoder($encoder)
    {
        self::$encoder = $encoder;
    }
}
