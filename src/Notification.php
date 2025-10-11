<?php

namespace PhpXmlRpc\JsonRpc;

class Notification extends Request
{
    /**
     * The constructor does not take a jsonRpcVersion arg, as this is a json-Rpc 2.0 notification.
     * @param string $methodName
     * @param  @param \PhpXmlRpc\Value[] $params
     */
    public function __construct($methodName, $params = array())
    {
        parent::__construct($methodName, $params);
    }

    /**
     * @return null
     */
    protected function generateId()
    {
        return null;
    }
}
