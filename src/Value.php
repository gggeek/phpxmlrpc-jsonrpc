<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\Value as BaseValue;

class Value extends BaseValue
{
    use SerializerAware;

    /**
     * Returns json representation of the value.
     *
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string
     */
    public function serialize($charsetEncoding = '')
    {
        return $this->getSerializer()->serializeValue($this, $charsetEncoding);
    }
}
