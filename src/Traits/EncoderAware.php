<?php

namespace PhpXmlRpc\JsonRpc\Traits;

use PhpXmlRpc\JsonRpc\Encoder;

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
