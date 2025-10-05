<?php

namespace PhpXmlRpc\JsonRpc;

class Notification extends Request
{
    public function __construct($methodName, $params = array())
    {
        parent::__construct($methodName, $params);
    }

    protected function generateId()
    {
        return null;
    }
}
