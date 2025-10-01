<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\Response as BaseResponse;

class Response extends BaseResponse
{
    use SerializerAware;

    protected $content_type = 'application/json';
    /// @todo make this protected, allowing access via __get and co
    public $id = null;

    protected $jsonrpc_version = PhpJsonRpc::VERSION_2_0;

    public function __construct($val, $fCode = 0, $fString = '', $valType = '', $id = null, $httpResponse = null)
    {
        $this->id = $id;

        parent::__construct($val, $fCode, $fString, $valType, $httpResponse);

        /// @todo throw exception if $valType is xml or xmlrpcvals ? Esp. valid for xml strings
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

    /**
     * Returns json representation of the response. Sets `payload` and `content_type` properties.
     *
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string the json representation of the response
     */
    public function serialize($charsetEncoding = '')
    {
        if ($charsetEncoding != '' && $charsetEncoding != 'UTF-8')
            $this->content_type = 'application/json; charset=' . $charsetEncoding;
        else
            $this->content_type = 'application/json';

        $this->payload = $this->getSerializer()->serializeResponse($this, $this->id, $charsetEncoding);
        return $this->payload;
    }

    /**
     * Reimplemented for completeness.
     *
     * @param string $charsetEncoding
     * @return string
     */
    public function xml_header($charsetEncoding = '')
    {
        return '';
    }
}
