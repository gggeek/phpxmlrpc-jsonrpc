<?php


namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Request as BaseRequest;

class Request extends BaseRequest
{
    public $id = null; // used to store request ID internally
    public $content_type = 'application/json';

    /**
     * @param string $methodName the name of the method to invoke
     * @param array $params array of parameters to be paased to the method (xmlrpcval objects)
     * @param mixed $id the id of the jsonrpc request
     */
    public function __construct($methodName, $params = 0, $id = null)
    {
        // NB: a NULL id is allowed and has a very definite meaning!
        $this->id = $id;
        parent::__construct($methodName, $params);
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
        // @ todo: verify if all chars are allowed for method names or can
        // we just skip the js encoding on it?
        $this->payload = "{\n\"method\": \"" . json_encode_entities($this->methodname, '', $charsetEncoding) . "\",\n\"params\": [ ";
        for ($i = 0; $i < sizeof($this->params); $i++) {
            $p = $this->params[$i];
            // MB: we try to force serialization as json even though the object
            // param might be a plain xmlrpcval object.
            // This way we do not need to override addParam, aren't we lazy?
            $this->payload .= "\n  " . serialize_jsonrpcval($p, $charsetEncoding) .
                ",";
        }
        $this->payload = substr($this->payload, 0, -1) . "\n],\n\"id\": ";
        switch (true) {
            case $this->id === null:
                $this->payload .= 'null';
                break;
            case is_string($this->id):
                $this->payload .= '"' . json_encode_entities($this->id, '', $charsetEncoding) . '"';
                break;
            case is_bool($this->id):
                $this->payload .= ($this->id ? 'true' : 'false');
                break;
            default:
                $this->payload .= $this->id;
        }
        $this->payload .= "\n}\n";
    }

    /**
     * Parse the jsonrpc response contained in the string $data and return a jsonrpcresp object.
     * @param string $data the xmlrpc response, eventually including http headers
     * @param bool $headersProcessed when true prevents parsing HTTP headers for interpretation of content-encoding and conseuqent decoding
     * @param string $returnType decides return type, i.e. content of response->value(). Either 'xmlrpcvals', 'xml' or 'phpvals'
     * @return Response
     */
    function parseResponse($data = '', $headersProcessed = false, $returnType = 'jsonrpcvals')
    {
        if ($this->debug) {
            print "<PRE>---GOT---\n" . htmlentities($data) . "\n---END---\n</PRE>";
        }

        if ($data == '') {
            error_log('XML-RPC: ' . __METHOD__ . ': no response received from server.');
            $r = new Response(0, $GLOBALS['xmlrpcerr']['no_data'], $GLOBALS['xmlrpcstr']['no_data']);
            return $r;
        }

        $GLOBALS['_xh'] = array();

        $raw_data = $data;
        // parse the HTTP headers of the response, if present, and separate them from data
        if (substr($data, 0, 4) == 'HTTP') {
            $r = $this->parseResponseHeaders($data, $headersProcessed);
            if ($r) {
                // parent class implementation of parseResponseHeaders returns in case
                // of error an object of the wrong type: recode it into correct object
                $rj = new Response(0, $r->faultCode(), $r->faultString());
                $rj->raw_data = $data;
                return $rj;
            }
        } else {
            $GLOBALS['_xh']['headers'] = array();
            $GLOBALS['_xh']['cookies'] = array();
        }

        if ($this->debug) {
            $start = strpos($data, '/* SERVER DEBUG INFO (BASE64 ENCODED):');
            if ($start !== false) {
                $start += strlen('/* SERVER DEBUG INFO (BASE64 ENCODED):');
                $end = strpos($data, '*/', $start);
                $comments = substr($data, $start, $end - $start);
                print "<PRE>---SERVER DEBUG INFO (DECODED) ---\n\t" . htmlentities(str_replace("\n", "\n\t", base64_decode($comments))) . "\n---END---\n</PRE>";
            }
        }

        // be tolerant of extra whitespace in response body
        $data = trim($data);

        // be tolerant of junk after methodResponse (e.g. javascript ads automatically inserted by free hosts)
        $end = strrpos($data, '}');
        if ($end) {
            $data = substr($data, 0, $end + 1);
        }
        // if user wants back raw json, give it to him
        if ($returnType == 'json') {
            $r = new Response($data, 0, '', 'json');
            $r->hdrs = $GLOBALS['_xh']['headers'];
            $r->_cookies = $GLOBALS['_xh']['cookies'];
            $r->raw_data = $raw_data;
            return $r;
        }

        // @todo shall we try to check for non-unicode json received ???

        if (!jsonrpc_parse_resp($data, $returnType == 'phpvals')) {
            if ($this->debug) {
                /// @todo echo something for user?
            }

            $r = new Response(0, $GLOBALS['xmlrpcerr']['invalid_return'],
                $GLOBALS['xmlrpcstr']['invalid_return'] . ' ' . $GLOBALS['_xh']['isf_reason']);
        }
        //elseif ($returnType == 'jsonrpcvals' && !is_object($GLOBALS['_xh']['value']))
        //{
        // then something odd has happened
        // and it's time to generate a client side error
        // indicating something odd went on
        //    $r =  new jsonrpcresp(0, $GLOBALS['xmlrpcerr']['invalid_return'],
        //        $GLOBALS['xmlrpcstr']['invalid_return']);
        //}
        else {
            $v = $GLOBALS['_xh']['value'];

            if ($this->debug) {
                print "<PRE>---PARSED---\n";
                var_export($v);
                print "\n---END---</PRE>";
            }

            if ($GLOBALS['_xh']['isf']) {
                $r = new Response(0, $v['faultCode'], $v['faultString']);
            } else {
                $r = new Response($v, 0, '', $returnType);
            }
            $r->id = $GLOBALS['_xh']['id'];
        }

        $r->hdrs = $GLOBALS['_xh']['headers'];
        $r->_cookies = $GLOBALS['_xh']['cookies'];
        $r->raw_data = $raw_data;
        return $r;
    }
}
