<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\PhpXmlRpc;

class PhpJsonRpc
{
    const VERSION_1_0 = '1.0';
    const VERSION_2_0 = '2.0';

    public static $jsonrpcName = "JSON-RPC for PHP";
    public static $jsonrpcVersion = "1.0.0-beta3";
    public static $defaultJsonrpcVersion = self::VERSION_2_0;

    public static $json_decode_flags = 0;
    public static $json_decode_depth = 512;
    //public static $json_encode_flags = 0;

    public static function setLogger($logger)
    {
        Charset::setLogger($logger);
        Client::setLogger($logger);
        Parser::setLogger($logger);
        Request::setLogger($logger);
        Server::setLogger($logger);
        Value::setLogger($logger);
        Wrapper::setLogger($logger);

        PhpXmlRpc::setLogger($logger);
    }
}
