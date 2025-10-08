<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Traits\JsonRpcVersionAware;
use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\Response as BaseResponse;

/// @todo introduce $responseClass, to allow subclasses to produce different types of response?
class Response extends BaseResponse
{
    use SerializerAware;
    use JsonRpcVersionAware;

    protected $content_type = 'application/json';
    protected $id = null;

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
     * Reimplemented to make us use the correct parser type.
     *
     * @return Charset
     */
    public function getCharsetEncoder()
    {
        if (self::$charsetEncoder === null) {
            self::$charsetEncoder = Charset::instance();
        }
        return self::$charsetEncoder;
    }

    /**
     * @return mixed
     */
    public function id()
    {
        return $this->id;
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

    /**
     * @param Response $resp
     * @param mixed $id
     * @return Response
     */
    public static function withId($resp, $id) {
        return new self($resp->value(), $resp->faultCode(), $resp->faultString(), $resp->valueType(), $id, $resp->httpResponse());
    }
}
