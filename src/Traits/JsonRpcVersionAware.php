<?php

namespace PhpXmlRpc\JsonRpc\Traits;

trait JsonRpcVersionAware
{
    /** @var string */
    protected $jsonrpc_version;

    /**
     * @param string $jsonrpcVersion
     * @return void
     */
    public function setJsonRpcVersion($jsonrpcVersion)
    {
        $this->jsonrpc_version = $jsonrpcVersion;
    }

    /**
     * @return string
     */
    public function getJsonRpcVersion()
    {
        return $this->jsonrpc_version;
    }
}
