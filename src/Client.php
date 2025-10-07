<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Client as BaseClient;
use PhpXmlRpc\Exception\ValueErrorException;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Traits\JsonRpcVersionAware;
use PhpXmlRpc\PhpXmlRpc;

/**
 * @todo the JSON proposed RFC states that when making json calls, we should specify an 'accept: application/json'
 *       http header. Currently, we either do not output an 'accept' header or specify 'any' (in curl mode)
 */
class Client extends BaseClient
{
    use JsonRpcVersionAware;

    protected static $requestClass = '\\PhpXmlRpc\\JsonRpc\\Request';
    protected static $responseClass = '\\PhpXmlRpc\\JsonRpc\\Response';

    const OPT_JSONRPC_VERSION = 'jsonrpc_version';

    protected static $extra_options = array(
        self::OPT_JSONRPC_VERSION,
    );

    // by default, no multicall exists for JSON-RPC, so do not try it
    public $no_multicall = true;

    // default return type of calls to json-rpc servers: jsonrpcvals
    public $return_type = Parser::RETURN_JSONRPCVALS;

    // according to https://tools.ietf.org/html/rfc8259#section-8.1, UTF8 is the rule
    public $request_charset_encoding = 'UTF-8';

    public function __construct($path, $server='', $port='', $method='')
    {
        parent::__construct($path, $server, $port, $method);

        // @todo we need to override the list of std supported encodings, since according to ECMA-262, the standard
        //       charset is UTF-16...
        //$this->accepted_charset_encodings = array('UTF-16', 'UTF-8', 'ISO-8859-1', 'US-ASCII');

        $this->user_agent =  PhpJsonRpc::$jsonrpcName . ' ' . PhpJsonRpc::$jsonrpcVersion . ' (' . PhpXmlRpc::$xmlrpcName . ' ' . PhpXmlRpc::$xmlrpcVersion . ')';
    }

    public function setOption($name, $value)
    {
        if (in_array($name, static::$options) || in_array($name, static::$extra_options)) {
            $this->$name = $value;
            return $this;
        }

        throw new ValueErrorException("Unsupported option '$name'");
    }

    /**
     * @param string $name see all the OPT_ constants
     * @return mixed
     * @throws ValueErrorException on unsupported option
     */
    public function getOption($name)
    {
        if (in_array($name, static::$options) || in_array($name, static::$extra_options)) {
            return $this->$name;
        }

        throw new ValueErrorException("Unsupported option '$name'");
    }

    /**
     * @param \PhpXmlRpc\JsonRpc\Request|\PhpXmlRpc\JsonRpc\Request[]|string $req
     * @param int $timeout deprecated
     * @param string $method deprecated
     * @return \PhpXmlRpc\JsonRpc\Response|true|\PhpXmlRpc\JsonRpc\Response[] true for notification calls, if the server
     *         returns an empty http response body
     */
    public function send($req, $timeout = 0, $method = '')
    {
        if ($this->jsonrpc_version != '') {
            if (is_array($req)) {
                foreach ($req as $i => $r) {
                    $req[$i]->setJsonRpcVersion($this->jsonrpc_version);
                }
            } elseif (!is_string($req)) {
                $req->setJsonRpcVersion($this->jsonrpc_version);
            }
        }

        return parent::send($req, $timeout, $method);
    }
}
