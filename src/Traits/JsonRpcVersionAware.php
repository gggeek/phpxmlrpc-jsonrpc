<?php

namespace PhpXmlRpc\JsonRpc\Traits;

trait JsonRpcVersionAware
{
    /** @var string|null */
    protected $jsonrpc_version;

    /**
     * @param string|null $jsonrpcVersion Use NULL to let the global var PhpJsonRpc::$defaultJsonRpcVersion decide
     * @return void
     */
    public function setJsonRpcVersion($jsonrpcVersion)
    {
        $this->jsonrpc_version = $jsonrpcVersion;
    }

    /**
     * @return string|null NULL means let the global var PhpJsonRpc::$defaultJsonRpcVersion decide
     */
    public function getJsonRpcVersion()
    {
        return $this->jsonrpc_version;
    }
}
