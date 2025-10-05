<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\Response as BaseResponse;

class Response extends BaseResponse
{
    use SerializerAware;

    protected $content_type = 'application/json';
    protected $id = null;
    protected $jsonrpc_version = PhpJsonRpc::VERSION_2_0;

    public function __construct($val, $fCode = 0, $fString = '', $valType = '', $id = null, $httpResponse = null)
    {
        // accommodate those methods which build a Response using the calling syntax of the PhpXmlRpc\Response class
        if ($httpResponse === null && is_array($id) && isset($id['raw_data'])) {
            $httpResponse = $id;
            $id = null;
        }

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
     * @return mixed
     */
    public function id()
    {
        return $this->id;
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

    // *** BC layer ***

    // we have to make this return by ref in order to allow calls such as `$resp->_cookies['name'] = ['value' => 'something'];`
    public function &__get($name)
    {
        switch ($name) {
            case 'id':
                $this->logDeprecation('Getting property Response::' . $name . ' is deprecated');
                return $this->$name;
            default:
                return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'id':
                $this->logDeprecation('Setting property Response::' . $name . ' is deprecated');
                $this->$name = $value;
                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        switch ($name) {
            case 'id':
                $this->logDeprecation('Checking property Response::' . $name . ' is deprecated');
                return isset($this->$name);
            default:
                return parent::__isset($name);
        }
    }

    public function __unset($name)
    {
        switch ($name) {
            case 'id':
                $this->logDeprecation('Unsetting property Response::' . $name . ' is deprecated');
                unset($this->$name);
                break;
            default:
                parent::__unset($name);
        }
    }
}
