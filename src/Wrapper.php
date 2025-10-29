<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Traits\JsonRpcVersionAware;
use PhpXmlRpc\Wrapper as BaseWrapper;

class Wrapper extends BaseWrapper
{
    use JsonRpcVersionAware;

    protected static $namespace = '\\PhpXmlRpc\\JsonRpc\\';
    protected static $prefix = 'jsonrpc';
    protected static $allowedResponseClass = '\\PhpXmlRpc\\Response';

    protected function buildClientWrapperCode($client, $verbatimClientCopy, $prefix = 'xmlrpc', $namespace = '\\PhpXmlRpc\\')
    {
        $code = parent::buildClientWrapperCode($client, $verbatimClientCopy, $prefix, $namespace);

        if ($this->jsonrpc_version !== null) {
            $code .= "\$client->setOption(\PhpXmlRpc\JsonRpc\Client::OPT_JSONRPC_VERSION, '" .
                str_replace("'", "\'", $this->jsonrpc_version) . "');\n";
        }

        return $code;
    }

    /**
     * @param Client $client
     * @return Client
     */
    protected function cloneClientForClosure($client)
    {
        $client = parent::cloneClientForClosure($client);

        if ($this->jsonrpc_version !== null) {
            $client->setOption(Client::OPT_JSONRPC_VERSION, $this->jsonrpc_version);
        }

        return $client;
    }
}
