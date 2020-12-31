<?php


namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Response as BaseResponse;

class Response extends BaseResponse
{
    public $content_type = 'application/json'; // NB: forces us to send US-ASCII over http
    public $id = null;

    /// @todo override creator, to set proper valtyp and id!

    /**
     * Returns json representation of the response.
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     * @return string the json representation of the response
     * @access public
     */
    function serialize($charsetEncoding = '')
    {
        if ($charsetEncoding != '')
            $this->content_type = 'application/json; charset=' . $charsetEncoding;
        else
            $this->content_type = 'application/json';
        $this->payload = serialize_jsonrpcresp($this, $this->id, $charsetEncoding);
        return $this->payload;
    }
}
