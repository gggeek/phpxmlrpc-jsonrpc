<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\Response as BaseResponse;

/**
 * @todo once we make php 5.4 a mandatory requirement, implement a SerializerAware trait
 */
class Response extends BaseResponse
{
    public $content_type = 'application/json'; // NB: forces us to send US-ASCII over http
    public $id = null;

    protected static $serializer;

    public function getSerializer()
    {
        if (self::$serializer === null) {
            self::$serializer = new Serializer();
        }
        return self::$serializer;
    }

    public static function setSerializer($serializer)
    {
        self::$serializer = $serializer;
    }

    public function __construct($val, $fCode = 0, $fString = '', $valType = '', $id = null)
    {
        $this->id = $id;

        /// @todo throw exception if $valType is xml or xmlrpcvals ?
        parent::__construct($val, $fCode, $fString, $valType);

        switch ($this->valtyp) {
            case 'xml':
                $this->valtyp = 'json';
                break;
            case 'xmlrpcvals':
                $this->valtyp = 'jsonrpcvals';
                break;
        }
    }

    /**
     * Returns json representation of the response.
     *
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string the json representation of the response
     */
    public function serialize($charsetEncoding = '')
    {
        if ($charsetEncoding != '')
            $this->content_type = 'application/json; charset=' . $charsetEncoding;
        else
            $this->content_type = 'application/json';
        $this->payload = $this->getSerializer()->serializeResponse($this, $this->id, $charsetEncoding);
        return $this->payload;
    }
}
