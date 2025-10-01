<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Exception\HttpException;
use PhpXmlRpc\Helper\Http;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Request as BaseRequest;

class Request extends BaseRequest
{
    use SerializerAware;

    public $id = null; // used to store request ID internally
    public $content_type = 'application/json';

    /** @var string */
    protected $jsonrpc_version = PhpJsonRpc::VERSION_2_0;
    protected $paramnames = array();

    /**
     * @param string $methodName the name of the method to invoke
     * @param \PhpXmlRpc\Value[] $params array of parameters to be passed to the method (xmlrpcval objects)
     * @param mixed $id the id of the json-rpc request. NB: a NULL id is allowed and has a very definite meaning!
     *
     * @todo if $id = null maybe we could assign it an incrementing unique-ish value, and allow another way to send
     *       notification requests?
     */
    public function __construct($methodName, $params = array(), $id = null)
    {
        $this->id = $id;
        parent::__construct($methodName, $params);
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
     * @return string[]
     */
    public function getParamNames()
    {
        return $this->getParamNames();
    }

    /**
     * @param $param
     * @param string|null $name
     * @return boolvoid
     */
    public function addParam($param, $name=null)
    {
        $this->paramnames[] = $name;
        $ok = parent::addParam($param);
        if ($ok) {
            $this->paramnames[] = $name;
        }
        return $ok;
    }

    /**
     * Reimplemented to make us use the correct parser type.
     *
     * @return Parser
     */
    public function getParser()
    {
        if (self::$parser === null) {
            self::$parser = new Parser();
        }
        return self::$parser;
    }

    /**
     * Reimplemented for completeness.
     * @internal this function will become protected in the future
     *
     * @param string $charsetEncoding
     * @return string
     */
    public function xml_header($charsetEncoding = '')
    {
        return '';
    }

    /**
     * Reimplemented for completeness.
     * @internal this function will become protected in the future
     *
     * @return string
     */
    public function xml_footer()
    {
        return '';
    }

    /**
     * @internal this function will become protected in the future
     *
     * @param string $charsetEncoding
     * @return void
     */
    public function createPayload($charsetEncoding = '')
    {
        if ($charsetEncoding != '' && $charsetEncoding != 'UTF-8')
            $this->content_type = 'application/json; charset=' . $charsetEncoding;
        else
            $this->content_type = 'application/json';

        $this->payload = $this->getSerializer()->serializeRequest($this, $this->id, $charsetEncoding);
    }

    /**
     * Parse the json-rpc response contained in the string $data and return a jsonrpcresp object.
     *
     * @param string $data the json-rpc response, possibly including http headers
     * @param bool $headersProcessed when true prevents parsing HTTP headers for interpretation of content-encoding and conseuqent decoding
     * @param string $returnType decides return type, i.e. content of response->value(). Either 'jsonrpcvals', 'json' or 'phpvals'
     * @return Response
     *
     * @todo move more of this parsing into the parent class (split method in smaller ones)
     * @todo throw when $returnType == 'xmlrpcvals', 'epivals' or 'xml'
     * @todo we should check that the received Id is the same s the one sent
     */
    public function parseResponse($data = '', $headersProcessed = false, $returnType = Parser::RETURN_JSONRPCVALS)
    {
        if ($this->debug) {
            $this->getLogger()->debug("---GOT---\n$data\n---END---");
        }

        $this->httpResponse = array('raw_data' => $data, 'headers' => array(), 'cookies' => array());

        if ($data == '') {
            $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': no response received from server.');
            return new Response(0, PhpXmlRpc::$xmlrpcerr['no_data'], PhpXmlRpc::$xmlrpcstr['no_data']);
        }

        // parse the HTTP headers of the response, if present, and separate them from data
        if (substr($data, 0, 4) == 'HTTP') {
            $httpParser = new Http();
            try {
                $httpResponse = $httpParser->parseResponseHeaders($data, $headersProcessed, $this->debug > 0);
            } catch (HttpException $e) {
                // failed processing of HTTP response headers
                // save into response obj the full payload received, for debugging
                return new Response(0, $e->getCode(), $e->getMessage(), '', null, array('raw_data' => $data, 'status_code', $e->statusCode()));
            } catch(\Exception $e) {
                return new Response(0, $e->getCode(), $e->getMessage(), '', null, array('raw_data' => $data));
            }
        } else {
            $httpResponse = $this->httpResponse;
        }

        // remove server comments _before_ we try to parse the returned json

        $serverComments = '';
        $userComments = '';
        if (strpos($data, '/* SERVER DEBUG INFO (BASE64 ENCODED):') === 0) {
            $start = strlen('/* SERVER DEBUG INFO (BASE64 ENCODED):');
            $end = strpos($data, '*/', $start);
            $serverComments = substr($data, $start, $end - $start);
            $data = ltrim(substr($data, $end + 2));
        }
        if (strpos($data, '/* DEBUG INFO:') === 0) {
            $start = strlen('/* DEBUG INFO:');
            $end = strpos($data, '*/', $start);
            $userComments = substr($data, $start, $end - $start);
            $data = substr($data, $end + 2);
        }

        // be tolerant of extra whitespace in response body
        $data = trim($data);

        /// @todo optimization creep - return an error msg if $data == ''

        // be tolerant of junk after methodResponse (e.g. javascript ads automatically inserted by free hosts)
        $end = strrpos($data, '}');
        if ($end) {
            $data = substr($data, 0, $end + 1);
        }

        // try to 'guestimate' the character encoding of the received response (even though it should be UTF-8, really)
        $respEncoding = Parser::guessEncoding(
            isset($httpResponse['headers']['content-type']) ? $httpResponse['headers']['content-type'] : '',
            $data
        );

        if ($this->debug >= 0) {
            $this->httpResponse = $httpResponse;
        } else {
            $httpResponse = null;
        }

        if ($this->debug) {
            if ($serverComments !== '') {
                $this->getLogger()->debug("---SERVER DEBUG INFO (DECODED)---\n\t" .
                    str_replace("\n", "\n\t", base64_decode($serverComments)) . "\n---END---");
            }
            if ($userComments !== '') {
                $this->getLogger()->debug("---SERVER DEBUG INFO---\n\t" .
                    str_replace("\n", "\n\t", $userComments) . "\n---END---", array('encoding' => $respEncoding));
            }
        }

        // if user wants back raw json, give it to her
        if ($returnType == 'json') {
            return new Response($data, 0, '', 'json', $httpResponse);
        }

        $options = array('target_charset' => PhpXmlRpc::$xmlrpc_internalencoding);
        if ($respEncoding != '') {
            $options['source_charset'] = $respEncoding;
        }

        $parser = $this->getParser();
        $_xh = $parser->parseResponse($data, $returnType, $options);
        // BC
        if (!is_array($_xh)) {
            $_xh = $parser->_xh;
        }

        // first error check: json not well formed
        if ($_xh['isf'] == 3) {
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'],
                PhpXmlRpc::$xmlrpcstr['invalid_return'] . ' ' . $_xh['isf_reason'], '', null, $httpResponse);

            if ($this->debug) {
                $this->getLogger()->debug($_xh['isf_reason']);
            }
        }
        // second error check: json well-formed but not json-rpc compliant
        elseif ($_xh['isf'] == 2) {
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'],
                PhpXmlRpc::$xmlrpcstr['invalid_return'] . ' ' . $_xh['isf_reason'], '', null, $httpResponse);

            if ($this->debug) {
                /// @todo echo something for user? check if it was already done by the parser...
            }
        }
        // third error check: parsing of the response has somehow gone boink.
        /// @todo shall we omit the 2nd part of this check, since we trust the parsing code?
        ///       Either that, or check the fault results too...
        elseif ($_xh['isf'] > 3 || ($returnType == Parser::RETURN_JSONRPCVALS && !$_xh['isf'] && !is_object($_xh['value']))) {
            // something odd has happened and it's time to generate a client side error indicating something odd went on
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'], PhpXmlRpc::$xmlrpcstr['invalid_return'],
                '', null, $httpResponse);
        } else {

            if ($this->debug > 1) {
                $this->getLogger()->debug(
                    "---PARSED---\n".var_export($_xh['value'], true)."\n---END---"
                );
            }

            $v = $_xh['value'];

            if ($_xh['isf']) {
                if ($v['faultCode'] == 0) {
                    // FAULT returned, errno needs to reflect that
                    /// @todo feature creep - add this code to PhpXmlRpc::$xmlrpcerr
                    $this->getLogger()->error('XML-RPC: ' . __METHOD__ . ': fault response received with faultCode 0 or null. Converted it to -1');
                    $v['faultCode'] = -1;
                }

                // unlike the xml-rpc parser, the json parser never wraps errors into Value objects
                $r = new Response(0, $v['faultCode'], $v['faultString'], '', null, $httpResponse);
            } else {
                $r = new Response($v, 0, '', $returnType, null, $httpResponse);
            }

            /// @todo check that received id is the same as the sent one
            /// @todo for jsonrpc 2.0, a null id should be treated as error
            $r->id = $_xh['id'];
        }

        if (isset($_xh['jsonrpc_version'])) {
            $r->setJsonRpcVersion($_xh['jsonrpc_version']);
        }

        return $r;
    }
}
