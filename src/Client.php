<?php


namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Client as BaseClient;

/**
 * @todo the JSON proposed RFC states that when making json calls, we should
 *       specify an 'accept: application/json' http header. Currently we either
 *       do not output an 'accept' header or specify 'any' (in curl mode)
 */
class Client extends BaseClient
{
    // by default, no multicall exists for JSON-RPC, so do not try it
    public $no_multicall = true;

    // default return type of calls to json-rpc servers: jsonrpcvals
    public $return_type = 'jsonrpcvals';

    /*
    function __construct($path, $server='', $port='', $method='')
    {
        parent::__construct($path, $server, $port, $method);
        // we need to override the list of std supported encodings, since
        // according to ECMA-262, the standard charset is UTF-16
        $this->accepted_charset_encodings = array('UTF-16', 'UTF-8', 'ISO-8859-1', 'US-ASCII');
    }
    */
}
