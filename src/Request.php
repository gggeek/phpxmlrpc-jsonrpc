<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Exception\HttpException;
use PhpXmlRpc\Helper\Http;
use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Traits\JsonRpcVersionAware;
use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Request as BaseRequest;

/// @todo introduce $responseClass, to allow subclasses to produce different types of response?
class Request extends BaseRequest
{
    use SerializerAware;
    use JsonRpcVersionAware;

    protected $id = null; // used to store request ID internally
    protected $content_type = 'application/json';
    /** @var string[] */
    protected $paramnames = array();
    protected $parsedResponseIsFromServer = false;

    protected static $currentIdCounter = 1;
    protected static $currentIdPrefix = '';

    /**
     * @param string $methodName the name of the method to invoke
     * @param \PhpXmlRpc\Value[] $params array of parameters to be passed to the method (Value objects).
     *                                   For json-rpc 2.0 calls, the array keys should either be all consecutive integers
     *                                   starting at 0, or be all strings, in which case the named-parameters calling
     *                                   convention will be used instead of the positional parameters one.
     *                                   For json-rpc 1.0 calls, the array keys get discarded, as only positional params
     *                                   are supported by the protocol.
     *                                   Note that \PhpXmlRpc\Value of type DateTime and Base64 will be serialized as
     *                                   json strings, but not decoded into the correct type at the receiving end.
     * @param null|string $jsonrpcVersion pass either PhpJsonRpc::VERSION_2_0 or PhpJsonRpc::VERSION_1_0 to force a value.
     *                                    If not set, the lib default value (set in PhpJsonRpc::$defaultJsonrpcVersion)
     *                                    will be used
     * @param mixed $id the id of the json-rpc request. A NULL value is allowed, in which case a unique id will be
     *                  generated. To send notifications, use the Notification class instead.
     */
    public function __construct($methodName, $params = array(), $id = null, $jsonrpcVersion = null)
    {
        if ($id === null) {
            $id = $this->generateId();
        }
        /// @todo if the version is 2.0, id should not be a bool value. Log a warning if it is
        $this->id = $id;

        if ($jsonrpcVersion !== null) {
            $this->jsonrpc_version = $jsonrpcVersion;
        } else {
            $this->jsonrpc_version = PhpJsonRpc::$defaultJsonrpcVersion;
        }

        //parent::__construct($methodName, $params);

        $this->methodname = $methodName;

        $useNamedParams = false;
        if ($this->jsonrpc_version == PhpJsonRpc::VERSION_2_0 && count($params)) {
            $i = 0;
            foreach($params as $name => $param) {
                if ($name !== $i) {
                    $useNamedParams = true;
                    break;
                }
                $i++;
            }
        }

        foreach ($params as $name => $param) {
            $this->addParam($param, $useNamedParams ? $name : null);
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
     * @param int $i
     * @return string A string is returned, as long as only string keys are used in the constructor and addParam calls.
     *                Does not check if the index is oyt of bounds.
     */
    public function getParamName($i)
    {
        return $this->paramnames[$i];
    }

    /**
     * @param $param
     * @param string|null $name Either all params should have a name, or none of them
     * @return bool
     */
    public function addParam($param, $name=null)
    {
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

        $this->payload = $this->getSerializer()->serializeRequest($this, $charsetEncoding);
    }

    /**
     * Parse the json-rpc response contained in the string $data and return a jsonrpcresp object.
     *
     * @param string $data the json-rpc response, possibly including http headers
     * @param bool $headersProcessed when true prevents parsing HTTP headers for interpretation of content-encoding and conseuqent decoding
     * @param string $returnType decides return type, i.e. content of response->value(). Either 'jsonrpcvals', 'json' or 'phpvals'
     * @return Response|true true when notifications are sent (and the server returns an http response with no body)
     *
     * @todo move more of this parsing into the parent class (split method in smaller ones)
     * @todo throw when $returnType == 'xmlrpcvals', 'epivals' or 'xml'
     * @todo we should check that the received Id is the same s the one sent
     */
    public function parseResponse($data = '', $headersProcessed = false, $returnType = Parser::RETURN_JSONRPCVALS)
    {
        $this->parsedResponseIsFromServer = false;

        if ($this->debug) {
            $this->getLogger()->debug("---GOT---\n$data\n---END---");
        }

        $this->httpResponse = array('raw_data' => $data, 'headers' => array(), 'cookies' => array());

        if ($data == '' && $this->id !== null) {
            $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': no response received from server.');
            return new Response(0, PhpXmlRpc::$xmlrpcerr['no_data'], PhpXmlRpc::$xmlrpcstr['no_data'], '', $this->id);
        }

        // parse the HTTP headers of the response, if present, and separate them from data
        if (substr($data, 0, 4) == 'HTTP') {
            $httpParser = new Http();
            if ($this->id === null) {
                // accept 204 responses when sending notifications
                $httpParser->setAcceptedStatusCodes(array('200', '204'));
            }
            try {
                $httpResponse = $httpParser->parseResponseHeaders($data, $headersProcessed, $this->debug > 0);
            } catch (HttpException $e) {
                // failed processing of HTTP response headers
                // save into response obj the full payload received, for debugging
                return new Response(0, $e->getCode(), $e->getMessage(), '', $this->id, array('raw_data' => $data, 'status_code' => $e->statusCode()));
            } catch (\Exception $e) {
                return new Response(0, $e->getCode(), $e->getMessage(), '', $this->id, array('raw_data' => $data));
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
        if ($data !== '' && ($data[0] === '{' || $data[0] === '[')) {
            if ($data[0] === '[') {
                $lc = ']';
            } else {
                $lc = '}';
            }
            $end = strrpos($data, $lc);
            if ($end) {
                $data = substr($data, 0, $end + 1);
            }
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

        if ($this->debug > 0) {
            if ($serverComments !== '') {
                $this->getLogger()->debug("---SERVER DEBUG INFO (DECODED)---\n\t" .
                    str_replace("\n", "\n\t", base64_decode($serverComments)) . "\n---END---");
            }
            if ($userComments !== '') {
                $this->getLogger()->debug("---SERVER DEBUG INFO---\n\t" .
                    str_replace("\n", "\n\t", $userComments) . "\n---END---", array('encoding' => $respEncoding));
            }
        }

        /// @todo is it correct to assume that all servers which accept notifications will return an empty http body?
        if ($this->id === null && $data === '') {
            return true;
        }

        // if user wants back raw json, give it to her
        // NB: in this case we inject $this->id even if it might differ in the received json
        /// @todo use constants
        if ($returnType == 'json' || $returnType == 'xml') {
            return new Response($data, 0, '', 'json', $this->id, $httpResponse);
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
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_xml'],
                PhpXmlRpc::$xmlrpcstr['invalid_xml'] . ' ' . $_xh['isf_reason'], '', $this->id, $httpResponse);

            if ($this->debug) {
                $this->getLogger()->debug($_xh['isf_reason']);
            }
        }
        // second error check: json well-formed but not json-rpc compliant
        elseif ($_xh['isf'] == 2) {
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['xml_not_compliant'],
                PhpXmlRpc::$xmlrpcstr['xml_not_compliant'] . ' ' . $_xh['isf_reason'], '', $this->id, $httpResponse);

            /// @todo echo something for user? check if it was already done by the parser...
            //if ($this->debug > 0) {
            //}
        }
        // third error check: parsing of the response has somehow gone boink.
        /// @todo shall we omit the 2nd part of this check, since we trust the parsing code?
        ///       Either that, or check the fault results too...
        elseif ($_xh['isf'] > 3 || ($returnType == Parser::RETURN_JSONRPCVALS && !$_xh['isf'] && !is_object($_xh['value']))) {
            // something odd has happened and it's time to generate a client side error indicating something odd went on
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['xml_parsing_error'], PhpXmlRpc::$xmlrpcstr['xml_parsing_error'],
                '', $this->id, $httpResponse);

            /// @todo echo something for the user?
        } else {

            if ($this->debug > 1) {
                $this->getLogger()->debug(
                    "---PARSED---\n".var_export($_xh['value'], true)."\n---END---"
                );
            }

            /// @todo for jsonrpc 2.0, a null id should be treated as error (check here or before?)

            /// @todo check if we got back a different json-rpc version that we sent, log a warning if we did

            // check that received id is the same as the one that was sent, unless we get back a null ID, which stands for
            // server-side errors
            if ($_xh['id'] !== null && $_xh['id'] != $this->id) {
                $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_xml'],
                    PhpXmlRpc::$xmlrpcstr['invalid_xml'] . ' The response Id does not match the request one', '', $this->id, $httpResponse);
            } else {
                $v = $_xh['value'];

                if ($_xh['isf']) {
                    if ($v['faultCode'] == 0) {
                        // FAULT returned, errno needs to reflect that
                        /// @todo feature creep - add this code to PhpXmlRpc::$xmlrpcerr
                        $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': fault response received with faultCode 0 or null. Converted it to -1');
                        $v['faultCode'] = -1;
                    }

                    // unlike the xml-rpc parser, the json parser never wraps errors into Value objects
                    $r = new Response(0, $v['faultCode'], $v['faultString'], '', $_xh['id'], $httpResponse);
                    $this->parsedResponseIsFromServer = true;
                } else {
                    $r = new Response($v, 0, '', $returnType, $_xh['id'], $httpResponse);
                    $this->parsedResponseIsFromServer = true;
                }
            }
        }

        if (isset($_xh['jsonrpc_version'])) {
            $r->setJsonRpcVersion($_xh['jsonrpc_version']);
        }

        return $r;
    }

    /**
     * @return string|int
     */
    protected function generateId()
    {
        $id = static::$currentIdPrefix . static::$currentIdCounter;
        if (static::$currentIdCounter == PHP_INT_MAX) {
            /// @todo use all hexadecimal letters
            static::$currentIdPrefix = 'a' . static::$currentIdPrefix;
            static::$currentIdCounter = 1;
        } else {
            static::$currentIdCounter++;
        }
        return $id;
    }

    /**
     * @return bool
     */
    public function parsedResponseIsFromServer()
    {
        return $this->parsedResponseIsFromServer;
    }

    /**
     * @return void
     */
    public function resetParsedResponseIsFromServer()
    {
        $this->parsedResponseIsFromServer = false;
    }
}
