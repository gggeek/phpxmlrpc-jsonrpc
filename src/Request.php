<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Helper\Http;
use PhpXmlRpc\Helper\Logger;
//use PhpXmlRpc\Helper\XMLParser;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Request as BaseRequest;

class Request extends BaseRequest
{
    public $id = null; // used to store request ID internally
    public $content_type = 'application/json';

    protected static $logger;
    protected static $parser;
    protected static $serializer;

    public function getLogger()
    {
        if (self::$logger === null) {
            self::$logger = Logger::instance();
        }
        return self::$logger;
    }

    public static function setLogger($logger)
    {
        self::$logger = $logger;
    }

    public function getParser()
    {
        if (self::$parser === null) {
            self::$parser = new Parser();
        }
        return self::$parser;
    }

    public static function setParser($parser)
    {
        self::$parser = $parser;
    }

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

    /**
     * @param string $methodName the name of the method to invoke
     * @param \PhpXmlRpc\Value[] $params array of parameters to be passed to the method (xmlrpcval objects)
     * @param mixed $id the id of the jsonrpc request. NB: a NULL id is allowed and has a very definite meaning!
     * @todo if $id = null maybe we could assign it an incrementing unique-ish value, and allow another way to send
     *       notification requests?
     */
    public function __construct($methodName, $params = array(), $id = null)
    {
        $this->id = $id;
        parent::__construct($methodName, $params);
    }

    /**
     * Reimplemented for completeness.
     * @internal this function will become protected in the future
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
     * @return string
     */
    public function xml_footer()
    {
        return '';
    }

    /**
     * @param string $charsetEncoding
     * @access protected
     */
    public function createPayload($charsetEncoding = '')
    {
        if ($charsetEncoding != '')
            $this->content_type = 'application/json; charset=' . $charsetEncoding;
        else
            $this->content_type = 'application/json';
        $this->payload = $this->getSerializer()->serializeRequest($this, $this->id, $charsetEncoding);
    }

    /**
     * Parse the jsonrpc response contained in the string $data and return a jsonrpcresp object.
     * @param string $data the xmlrpc response, eventually including http headers
     * @param bool $headersProcessed when true prevents parsing HTTP headers for interpretation of content-encoding and conseuqent decoding
     * @param string $returnType decides return type, i.e. content of response->value(). Either 'jsonrpcvals', 'json' or 'phpvals'
     * @return Response
     * @todo move more of this parsing into the parent class (split method in smaller ones)
     * @todo throw when $returnType == 'xmlrpcvals', 'epivals' or 'xml'
     * @todo we should check that the received Id is the same s the one sent
     */
    public function parseResponse($data = '', $headersProcessed = false, $returnType = 'jsonrpcvals')
    {
        if ($this->debug) {
            $this->getLogger()->debugMessage("---GOT---\n$data\n---END---");
        }

        $this->httpResponse = array('raw_data' => $data, 'headers' => array(), 'cookies' => array());

        if ($data == '') {
            error_log('XML-RPC: ' . __METHOD__ . ': no response received from server.');
            return new Response(0, PhpXmlRpc::$xmlrpcerr['no_data'], PhpXmlRpc::$xmlrpcstr['no_data']);
        }

        // parse the HTTP headers of the response, if present, and separate them from data
        if (substr($data, 0, 4) == 'HTTP') {
            $httpParser = new Http();
            try {
                $this->httpResponse = $httpParser->parseResponseHeaders($data, $headersProcessed, $this->debug);
            } catch(\Exception $e) {
                $r = new Response(0, $e->getCode(), $e->getMessage());
                // failed processing of HTTP response headers
                // save into response obj the full payload received, for debugging
                $r->raw_data = $data;

                return $r;
            }
        }

        // remove server comments _before_ we try to parse the returned json

        $serverComments = '';
        $userComments = '';
        if (strpos($data, '/* SERVER DEBUG INFO (BASE64 ENCODED):') === 0) {
            $start = strlen('/* SERVER DEBUG INFO (BASE64 ENCODED):');
            $end = strpos($data, '*/', $start);
            $serverComments = substr($data, $start, $end - $start);
            $data = substr($data, $end + 2);

        }
        if (strpos($data, '/* SERVER DEBUG INFO:') === 0) {
            $start = strlen('/* DEBUG INFO:');
            $end = strpos($data, '*/', $start);
            $userComments = substr($data, $start, $end - $start);
            $data = substr($data, $end + 2);
        }

        // be tolerant of extra whitespace in response body
        $data = trim($data);

        /// @todo return an error msg if $data == '' ?

        // be tolerant of junk after methodResponse (e.g. javascript ads automatically inserted by free hosts)
        $end = strrpos($data, '}');
        if ($end) {
            $data = substr($data, 0, $end + 1);
        }

        // @todo shall we try to check for non-unicode json received ???

        // try to 'guestimate' the character encoding of the received response
        /// @todo optimize the check for charset declaration in the json text
        //$respEncoding = XMLParser::guessEncoding(@$this->httpResponse['headers']['content-type'], $data);

        if ($this->debug) {
            if ($serverComments !== '') {
                $this->getLogger()->debugMessage("---SERVER DEBUG INFO (DECODED) ---\n\t" .
                    str_replace("\n", "\n\t", base64_decode($serverComments)) . "\n---END---");
            }
            if ($userComments !== '') {
                $this->getLogger()->debugMessage("---SERVER DEBUG INFO ---\n\t" .
                    str_replace("\n", "\n\t", $userComments) . "\n---END---");
            }
        }

        // if user wants back raw json, give it to her
        if ($returnType == 'json') {
            $r = new Response($data, 0, '', 'json');
            $r->hdrs = $this->httpResponse['headers'];
            $r->_cookies = $this->httpResponse['cookies'];
            $r->raw_data = $this->httpResponse['raw_data'];

            return $r;
        }

        //if ($respEncoding != '') {
        //}

        $parser = $this->getParser();
        $parser->parseResponse($data, $returnType == 'phpvals');

        // first error check: json not well formed
        if ($parser->_xh['isf'] > 2) {
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'],
                PhpXmlRpc::$xmlrpcstr['invalid_return'] . ' ' . $parser->_xh['isf_reason']);

            if ($this->debug) {
                print $parser->_xh['isf_reason'];
            }
        }
        // second error check: json well formed but not json-rpc compliant
        elseif ($parser->_xh['isf'] == 2) {
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'],
                PhpXmlRpc::$xmlrpcstr['invalid_return'] . ' ' . $parser->_xh['isf_reason']);

            if ($this->debug) {
                /// @todo echo something for user?
            }
        }
        // third error check: parsing of the response has somehow gone boink.
        /// @todo shall we omit this check, since we trust the parsing code?
        elseif ($returnType == 'jsonrpcvals' && !is_object($parser->_xh['value']) && $parser->_xh['isf'] == 0) {
            // something odd has happened
            // and it's time to generate a client side error
            // indicating something odd went on
            $r = new Response(0, PhpXmlRpc::$xmlrpcerr['invalid_return'],
                PhpXmlRpc::$xmlrpcstr['invalid_return']);
        } else {

            if ($this->debug > 1) {
                $this->getLogger()->debugMessage(
                    "---PARSED---\n".var_export($parser->_xh['value'], true)."\n---END---"
                );
            }

            $v = $parser->_xh['value'];

            if ($parser->_xh['isf']) {
                $r = new Response(0, $v['faultCode'], $v['faultString']);
            } else {
                $r = new Response($v, 0, '', $returnType);
            }

            $r->id = $parser->_xh['id'];
        }

        $r->hdrs = $this->httpResponse['headers'];
        $r->_cookies = $this->httpResponse['cookies'];
        $r->raw_data = $this->httpResponse['raw_data'];

        return $r;
    }
}
