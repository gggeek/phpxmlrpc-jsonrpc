<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Exception\NoSuchMethodException;
use PhpXmlRpc\Helper\Interop;
use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Traits\EncoderAware;
use PhpXmlRpc\JsonRpc\Traits\SerializerAware;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Server as BaseServer;

/**
 * @todo implement dispatching of multicall requests, json way
 * @todo test system.XXX methods, with special care to multicall
 * @todo support 'notification' calls, i.e. if id is null, echo back nothing
 * @todo should we override all parent's methods related to multicall which do not work for us?
 */
class Server extends BaseServer
{
    use EncoderAware;
    use SerializerAware;

    const OPT_DEBUG_FORMAT = 'debug_format';

    protected static $jsonRpcParser;
    protected static $jsonRpcCharsetEncoder;

    protected static $responseClass = '\\PhpXmlRpc\\JsonRpc\\Response';

    //public $allow_system_funcs = false;
    protected $functions_parameters_type = Parser::RETURN_JSONRPCVALS;

    /**
     * @var array
     * Option used for fine-tuning the encoding the php values returned from functions registered in the dispatch map
     * when the functions_parameters_type member is set to 'phpvals'.
     * @see Encoder::encode for a list of values
     */
    protected $phpvals_encoding_options = array();

    protected $debug = 0;

    /** @var null|bool This is required as checking for NULL resp. Id is not sufficient - it can happen in error cases */
    protected $is_servicing_notification = null;
    /** @var null|false|array */
    protected $is_servicing_multicall = null;

    /** @var string either 'json5' or 'extra_member' */
    protected $debug_format = 'json5';

    /**
     * Reimplemented to make us use the correct charset encoder type.
     *
     * @return Charset
     */
    public function getCharsetEncoder()
    {
        if (self::$jsonRpcCharsetEncoder === null) {
            self::$jsonRpcCharsetEncoder = Charset::instance();
        }
        return self::$jsonRpcCharsetEncoder;
    }

    public static function setCharsetEncoder($charsetEncoder)
    {
        self::$jsonRpcCharsetEncoder = $charsetEncoder;
    }

    /**
     * Reimplemented to make us use the correct parser type.
     *
     * @return Parser
     */
    public function getParser()
    {
        if (self::$jsonRpcParser === null) {
            self::$jsonRpcParser = new Parser();
        }
        return self::$jsonRpcParser;
    }

    public static function setParser($parser)
    {
        self::$jsonRpcParser = $parser;
    }

    /**
     * @string $charsetEncoding
     * @string $format either 'comment' (will generate js comments) or 'string' (will generate a js string)
     * @todo allow to (optionally) send comments as top-level json element, since 99.99% of json parsers will barf on js comments...
     */
    public function serializeDebug($charsetEncoding = '', $format = 'comment')
    {
        $out = '';
        if ($this->debug_info != '') {
            if ($format === 'string') {
                $out .= "SERVER DEBUG INFO (BASE64 ENCODED): " . base64_encode($this->debug_info);
            } else {
                $out .= "/* SERVER DEBUG INFO (BASE64 ENCODED):\n" . base64_encode($this->debug_info) . "\n*/\n";
            }
        }
        if (static::$_xmlrpc_debuginfo != '') {
            if ($format === 'string') {
                $out .= ($out === '' ? '' : ' ') . "DEBUG INFO: " . Charset::instance()->encodeEntities(static::$_xmlrpc_debuginfo, PhpXmlRpc::$xmlrpc_internalencoding, $charsetEncoding);
            } else {
                // make sure the user's comments can not break the JS comment
                $out .= "/* DEBUG INFO:\n\n" . str_replace('*/', '*\u002f', Charset::instance()->encodeEntities(static::$_xmlrpc_debuginfo, PhpXmlRpc::$xmlrpc_internalencoding, $charsetEncoding)) . "\n*/\n";
            }
        }

        if ($format === 'string' && $out !== '') {
            $out = '"' . $out . '"';
        }

        return $out;
    }

    /**
     * Execute the json-rpc request, printing the response.
     *
     * @param string $data the request body. If null, the http POST request will be examined
     * @param bool $returnPayload When true, return the response but do not echo it or any http header
     *
     * @return Response|string the response object (usually not used by caller...) or its json serialization
     * @throws \Exception in case the executed method does throw an exception (and depending on server configuration)
     */
    public function service($data = null, $returnPayload = false)
    {
        $this->is_servicing_notification = false;
        $this->is_servicing_multicall = false;
        $out = parent::service($data, $returnPayload);
        $this->is_servicing_notification = null;
        $this->is_servicing_multicall = null;
        return $out;
    }

    /**
     * Parse http headers received along with the json-rpc request. If needed, inflate request.
     *
     * @return Response|null null on success or an error Response
     */
    public function parseRequestHeaders(&$data, &$reqEncoding, &$respEncoding, &$respCompression)
    {
        $resp = parent::parseRequestHeaders($data, $reqEncoding, $respEncoding, $respCompression);

        if ($resp != null) {
            $this->fixErrorCodeIfNeeded($resp);
        }

        return $resp;
    }

    /**
     * Note: syntax differs from overridden method, by adding msgID and jsonrpcVersion args.
     *
     * @param Request|string $req either a Request obj or a method name
     * @param null|mixed[] $params unlike the parent class, the array can have non-numeric keys
     * @param null|string[] $paramTypes array with xml-rpc types of method parameters (only if $req is method name)
     * @param mixed $msgID to be used when $req is a string, or when it is an xml-rpc request instead of a json-rpc one
     * @param null|string $jsonrpcVersion when not set, the version from $req is used (if set), or the default one from PhpJsonRpc
     * @return Response
     * @throws \Exception
     */
    protected function execute($req, $params = null, $paramTypes = null, $msgID = null, $jsonrpcVersion = null)
    {
        static::$_xmlrpcs_occurred_errors = '';
        static::$_xmlrpc_debuginfo = '';

        if (is_object($req)) {
            $methodName = $req->method();
            if (is_callable(array($req, 'id'))) {
                $reqId = $req->id();
                if ($reqId !== null) {
                    if ($msgID !== null) {
                        $this->getLogger()->warning("JSON-RPC: " . __METHOD__ . ": msgID arg $msgID differs from request Id $reqId");
                    } else {
                        $msgID = $reqId;
                    }
                }
            }
            if (is_callable([$req, 'getJsonRpcVersion'])) {
                $reqJsonRpcVersion = $req->getJsonRpcVersion();
                if ($reqJsonRpcVersion !== null) {
                    if ($jsonrpcVersion !== null) {
                        $this->getLogger()->warning("JSON-RPC: " . __METHOD__ . ": jsonrpcVersion arg $jsonrpcVersion differs from request version $reqJsonRpcVersion");
                    } else {
                        $jsonrpcVersion = $reqJsonRpcVersion;
                    }
                }
            }
        } else {
            $methodName = $req;
        }

        $sysCall = $this->isSyscall($methodName);
        $dmap = $sysCall ? $this->getSystemDispatchMap() : $this->dmap;

        if (!isset($dmap[$methodName]['function'])) {
            // No such method
            $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['unknown_method'], PhpXmlRpc::$xmlrpcstr['unknown_method'], '', $msgID);
            if ($jsonrpcVersion != null) {
                $r->setJsonRpcVersion($jsonrpcVersion);
            }
            $this->fixErrorCodeIfNeeded($r);
            return $r;
        }

        // Check signature
        if (isset($dmap[$methodName]['signature'])) {
            $sig = $dmap[$methodName]['signature'];
            if (is_object($req)) {
                list($ok, $errStr) = $this->verifySignature($req, $sig);
            } else {
                list($ok, $errStr) = $this->verifySignature($paramTypes, $sig);
            }
            if (!$ok) {
                // Didn't match.
                $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['incorrect_params'],
                    PhpXmlRpc::$xmlrpcstr['incorrect_params'] . ": {$errStr}", '', $msgID
                );
                if ($jsonrpcVersion != null) {
                    $r->setJsonRpcVersion($jsonrpcVersion);
                }
                $this->fixErrorCodeIfNeeded($r);
                return $r;
            }
        }

        $func = $dmap[$methodName]['function'];

        // let the 'class::function' syntax be accepted in dispatch maps
        if (is_string($func) && strpos($func, '::')) {
            $func = explode('::', $func);
        }

        // build string representation of function 'name'
        if (is_array($func)) {
            if (is_object($func[0])) {
                $funcName = get_class($func[0]) . '->' . $func[1];
            } else {
                $funcName = implode('::', $func);
            }
        } else if ($func instanceof \Closure) {
            $funcName = 'Closure';
        } else {
            $funcName = $func;
        }

        // verify that function to be invoked is in fact callable
        if (!is_callable($func)) {
            $this->getLogger()->error("JSON-RPC: " . __METHOD__ . ": function $funcName registered as method handler is not callable");
            $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['server_error'],
                PhpXmlRpc::$xmlrpcstr['server_error'] . ': no function matches method', '', $msgID
            );
            if ($jsonrpcVersion != null) {
                $r->setJsonRpcVersion($jsonrpcVersion);
            }
            $this->fixErrorCodeIfNeeded($r);
            return $r;
        }

        if (isset($dmap[$methodName]['exception_handling'])) {
            $exception_handling = (int)$dmap[$methodName]['exception_handling'];
        } else {
            $exception_handling = $this->exception_handling;
        }

        // We always catch all errors generated during processing of user function, and log them as part of response;
        // if debug level is 3 or above, we also serialize them in the response as comments
        self::$_xmlrpcs_prev_ehandler = set_error_handler(array('\PhpXmlRpc\JsonRpc\Server', '_xmlrpcs_errorHandler'));

        /// @todo what about using output-buffering as well, in case user code echoes anything to screen?

        try {
            if (is_object($req)) {
                if ($sysCall) {
                    $r = call_user_func($func, $this, $req);
                } else {
                    $r = call_user_func($func, $req);
                }
                if (!is_a($r, 'PhpXmlRpc\Response')) {
                    $this->getLogger()->error("JSON-RPC: " . __METHOD__ . ": function $func registered as method handler does not return an xmlrpc response object");
                    if (is_a($r, 'PhpXmlRpc\Value')) {
                        $r = new static::$responseClass($r);
                    } else {
                        $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['server_error'],
                            PhpXmlRpc::$xmlrpcstr['server_error'] . ": function does not return json-rpc or xmlrpc response object"
                        );
                    }
                }
            } else {
                // call a 'plain php' function
/// @todo if php < 8.0 and $params is not numerically indexed, log an error message
                if ($sysCall) {
                    array_unshift($params, $this);
                    $r = call_user_func_array($func, $params);
                } else {
                    // 3rd API convention for method-handling functions: EPI-style
                    if ($this->functions_parameters_type == 'epivals') {
                        $r = call_user_func_array($func, array($methodName, $params, $this->user_data));
                        // mimic EPI behaviour: if we get an array that looks like an error, make it
                        // an error response
                        if (is_array($r) && array_key_exists('faultCode', $r) && array_key_exists('faultString', $r)) {
                            $r = new static::$responseClass(0, (int)$r['faultCode'], (string)$r['faultString']);
                        } else {
                            // functions using EPI api should NOT return resp objects,
                            // so make sure we encode the return type correctly
                            $r = new static::$responseClass($this->getEncoder()->encode($r, array('extension_api')));
                        }
                    } else {
                        $r = call_user_func_array($func, $params);
                    }
                }
                // the return type can be either a Response object or a plain php value...
                if (!is_a($r, 'PhpXmlRpc\Response')) {
                    // what should we assume here about automatic encoding of datetimes
                    // and php classes instances???
                    $r = new static::$responseClass($this->getEncoder()->encode($r, $this->phpvals_encoding_options));
                }
            }
            // here $r is either an xml-rpc response or a json-rpc response
            if (is_a($r, 'PhpXmlRpc\JsonRpc\Response')) {
                $r = call_user_func_array(array(static::$responseClass, 'withId'), array($r, $msgID));
            } else {

                // Dirty trick!!!
                // User has given us back an xml-rpc response, since he had an existing xml-rpc server with boatloads of code.
                // Be nice to him, and serialize the xml-rpc stuff into JSON.
                $r = new static::$responseClass($r->value(), $r->faultCode(), $r->faultString(), $r->valueType(), $msgID, $r->httpResponse());
            }
        } catch (\Exception $e) {
            // (barring errors in the lib) an uncatched exception happened in the called function, we wrap it in a
            // proper error-response
            switch ($exception_handling) {
                case 2:
                    if (self::$_xmlrpcs_prev_ehandler) {
                        set_error_handler(self::$_xmlrpcs_prev_ehandler);
                        self::$_xmlrpcs_prev_ehandler = null;
                    } else {
                        restore_error_handler();
                    }
                    throw $e;
                case 1:
                    $errCode = $e->getCode();
                    if ($errCode == 0) {
                        $errCode = PhpXmlRpc::$xmlrpcerr['server_error'];
                    }
                    $r = new static::$responseClass(0, $errCode, $e->getMessage(), '', $msgID);
                    break;
                default:
                    $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['server_error'], PhpXmlRpc::$xmlrpcstr['server_error'], '', $msgID);
            }
        } catch (\Error $e) {
            // (barring errors in the lib) an uncatched exception happened in the called function, we wrap it in a
            // proper error-response
            switch ($exception_handling) {
                case 2:
                    if (self::$_xmlrpcs_prev_ehandler) {
                        set_error_handler(self::$_xmlrpcs_prev_ehandler);
                        self::$_xmlrpcs_prev_ehandler = null;
                    } else {
                        restore_error_handler();
                    }
                    throw $e;
                case 1:
                    $errCode = $e->getCode();
                    if ($errCode == 0) {
                        $errCode = PhpXmlRpc::$xmlrpcerr['server_error'];
                    }
                    $r = new static::$responseClass(0, $errCode, $e->getMessage(), '', $msgID);
                    break;
                default:
                    $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['server_error'], PhpXmlRpc::$xmlrpcstr['server_error'], '', $msgID);
            }
        }

        // note: restore the error handler we found before calling the user func, even if it has been changed inside
        // the func itself
        if (self::$_xmlrpcs_prev_ehandler) {
            set_error_handler(self::$_xmlrpcs_prev_ehandler);
        } else {
            restore_error_handler();
        }

        if ($jsonrpcVersion != null) {
            $r->setJsonRpcVersion($jsonrpcVersion);
        }
        if ($exception_handling != 1 && $exception_handling != 2) {
            $this->fixErrorCodeIfNeeded($r);
        }

        return $r;
    }

    /**
     * @param string $data
     * @param string $reqEncoding
     * @return Response|\PhpXmlRpc\Response
     * @throws \Exception
     *
     * @internal this function will become protected in the future
     */
    public function parseRequest($data, $reqEncoding = '')
    {
        $parser = $this->getParser();

        $options = array('target_charset' => PhpXmlRpc::$xmlrpc_internalencoding);
        if ($reqEncoding != '') {
            $options['source_charset'] = $reqEncoding;
        }
        // register a callback with the parser for when it finds the method name
        $options['methodname_callback'] = array($this, 'methodNameCallback');

        try {
            $ok = $parser->parseRequest($data, $this->functions_parameters_type, $options);
        } catch (NoSuchMethodException $e) {
            // inject both the req. id and protocol version
            // NB: even if this was a notification, we send back a full response
            // NB: we access directly $parser->_xh because the assignment above did not execute
            $_xh = $parser->_xh;
            $resp = new static::$responseClass(0, $e->getCode(), $e->getMessage(), '', $_xh['id']);
            $resp->setJsonRpcVersion($_xh['jsonrpc_version']);
            $this->fixErrorCodeIfNeeded($resp);
            return $resp;
        }

        // BC we now get an array, we did use to get true/false
        if (is_array($ok)) {
            $_xh = $ok;
            $ok = $_xh['isf'] == 0;
        } else {
            $_xh = $parser->_xh;
        }
        if (!$ok) {
            if ($_xh['isf'] == 3 && preg_match('/^JSON parsing failed. Error: ([0-9]+)/', $_xh['isf_reason'], $matches)) {
                /// @see error values from https://www.php.net/manual/en/function.json-last-error.php
                ///      The values are known to go from 1 (JSON_ERROR_DEPTH) to 16 (JSON_ERROR_NON_BACKED_ENUM)
                $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerrxml + (int)$matches[1],
                    $_xh['isf_reason'], '', $_xh['id']);
            } else {
                $r = new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['invalid_request'],
                    PhpXmlRpc::$xmlrpcstr['invalid_request'] . ' ' . $_xh['isf_reason'], '', $_xh['id']);
            }
            $this->fixErrorCodeIfNeeded($r);
        } else {

            if ($_xh['id'] === null) {
                if ($_xh['is_multicall']) {
                    $this->is_servicing_multicall = $_xh['is_multicall'];
                } else {
                    $this->is_servicing_notification = true;
                }
            }

            if ($this->functions_parameters_type == 'phpvals' || $this->functions_parameters_type == 'epivals' ||
                (isset($this->dmap[$_xh['method']]['parameters_type']) &&
                ($this->dmap[$_xh['method']]['parameters_type'] == 'phpvals' || $this->dmap[$_xh['method']]['parameters_type'] == 'epivals'))
            ) {
                if ($this->debug > 1) {
                    $this->debugMsg("\n+++PARSED+++\n" . var_export($_xh['params'], true) . "\n+++END+++");
                }
                $r = $this->execute($_xh['method'], $_xh['params'], $_xh['pt'], $_xh['id'], $_xh['jsonrpc_version']);
            } else {
                // build a json-rpc Request object with data parsed from json
                $m = new Request($_xh['method'], array(), $_xh['id'], $_xh['jsonrpc_version']);
                // now add parameters in
                /// @todo for more speed, we could just pass in the array to the constructor (and loose the type validation)...
                $useNamedParams = false;
                $i = 0;
                foreach ($_xh['params'] as $name => $param) {
                    if ($name !== $i) {
                        $useNamedParams = true;
                        break;
                    }
                    $i++;
                }
                foreach ($_xh['params'] as $name => $param) {
                    $m->addParam($_xh['params'][$name], $useNamedParams ? $name : null);
                }

                if ($this->debug > 1) {
                    $this->debugMsg("\n+++PARSED+++\n" . var_export($m, true) . "\n+++END+++");
                }

                $r = $this->execute($m);
            }
        }

        return $r;
    }

    /**
     * @param Response $resp
     * @return void
     */
    protected function fixErrorCodeIfNeeded($resp)
    {
        // Json-rpc 2.0 responses use the same error codes as the xml-rpc interop ones.
        // We fix them without changing the global error codes, in case there are some xml-rpc calls being answered, too
        if (($errCode = $resp->faultCode()) != 0 && ($resp->getJsonRpcVersion() === PhpJsonRpc::VERSION_2_0 ||
            $resp->getJsonRpcVersion() == '' && PhpJsonRpc::$defaultJsonrpcVersion === PhpJsonRpc::VERSION_2_0)) {
            // We use 20 as a hardcoded limit because 1. atm the highest known json error code is 16; 2. json error
            // codes do get added in new php versions, so we can not know the future max errors, and 3. using a php
            // constant can not do, as it won't be defined in the min php version we support (atm 5.4)
            if ($errCode >= PhpXmlRpc::$xmlrpcerrxml && $errCode <= PhpXmlRpc::$xmlrpcerrxml + 20) {
                $errCode = PhpXmlRpc::$xmlrpcerr['invalid_xml'];
            }
            $errKeys = array_flip(PhpXmlRpc::$xmlrpcerr);
            if (isset($errKeys[$errCode]) && isset(Interop::$xmlrpcerr[$errKeys[$errCode]])) {
                /// @todo do not use deprecated property accessor to set this value
                $resp->errno = Interop::$xmlrpcerr[$errKeys[$errCode]];
            }
        }
    }

    /**
     * @param Response $resp
     * @param string $respCharset
     * @return string
     */
    protected function generatePayload($resp, $respCharset)
    {
        if ($this->is_servicing_notification) {
            $payload = '';

        } elseif ($this->is_servicing_multicall) {
            $serializer = $this->getSerializer();
            $payloads = [];

            /** @var value $subRespValue */
            foreach($resp->value() as $i => $subRespValue) {
                $id = $this->is_servicing_multicall[$i];

                // handle errors, and notifications
                if ($subRespValue->kindOf() === 'struct') {
                    $subResp = new Response(null, $subRespValue['faultCode']->scalarVal(), $subRespValue['faultString']->scalarVal(), '', $id);
                } else {
                    if ($id === null) {
                        continue;
                    } else {
                        $subResp = new Response($subRespValue[0], 0, '', '', $id);
                    }
                }
                // since json-rpc 1.0 does not specify batch call support, we force responses to be json-rpc 2.0
                $subResp->setJsonRpcVersion(PhpJsonRpc::VERSION_2_0);
                $payloads[] = $serializer->serializeResponse($subResp, $respCharset);
            }

/// @todo what about injecting debug info?
            $payload = $payloads ? ("[\n" . implode(",\n", $payloads) . "\n]") : '';
        } else {
            // instead of calling parent::generatePayload we do it online, as we have a different way of mixing debug info
            // into the output

            // Do not create response serialization if it has already happened.
            /// @todo what if the payload was created targeting a different charset than $respCharset?
            ///       Also, if we do not call serialize(), the request will not set its content-type to have the charset declared
            $payload = $resp->getPayload();
            if (empty($payload)) {
                $payload = $resp->serialize($respCharset);
            }
        }

        if ($this->debug > 0) {
            $payload = $this->injectDebugInfo($payload, $respCharset);
        }

        return $payload;
    }

    /**
     * @param string $payload
     * @param string $respCharset
     * @return string
     */
    protected function injectDebugInfo($payload, $respCharset)
    {
        switch ($this->debug_format) {
            case 'json5':
            case 'above':
                return $this->serializeDebug($respCharset) . $payload;
            case 'inline':
            case 'extra_member':
                if (strlen($payload)) {
                    if ($payload[0] === '{') {
                        // 'plain' response
                        return "{\n\"debug_info\": " . $this->serializeDebug($respCharset, 'string') . "," .substr($payload, 1);
                    } elseif ($payload[0] === '[') {
                        // 'batch' response
/// @todo is this a good idea? How will the client cope with an extra string in the array?
                        return "[\n" . $this->serializeDebug($respCharset, 'string') . "," . substr($payload, 1);
                    } else {
                        $this->getLogger()->warning("JSON-RPC: " . __METHOD__ . ": unsupported response payload starting with '$payload[0]'");
                        return $this->serializeDebug($respCharset) . $payload;
                    }
                } else {
                    // notification response
                    return "{\ndebug_info: " . $this->serializeDebug($respCharset, 'string') . "\n}";
                }
            default:
                $this->getLogger()->warning("JSON-RPC: " . __METHOD__ . ": unsupported format {$this->debug_format}");
                return $this->serializeDebug($respCharset) . $payload;
        }
    }

    /**
     * @param string $payload
     * @param string $respContentType
     * @param string $respEncoding
     * @return void
     */
    protected function printPayload($payload, $respContentType, $respEncoding)
    {
        if ($this->is_servicing_notification || ($this->is_servicing_multicall && $payload === '')) {
            // return a 204 response with no body

            if (!headers_sent()) {
                http_response_code(204);
            } else {
                $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': http headers already sent before response is fully generated. Check for php warning or error messages');
            }

            return;
        }

        parent::printPayload($payload, $respContentType, $respEncoding);
    }

    /**
     * @param string $methodName
     * @param Parser $xmlParser
     * @param null $parser
     * @return void
     * @throws NoSuchMethodException
     */
    public function methodNameCallback($methodName, $xmlParser, $parser = null)
    {
        $sysCall = $this->isSyscall($methodName);
        $dmap = $sysCall ? $this->getSystemDispatchMap() : $this->dmap;

        if (!isset($dmap[$methodName]['function'])) {
            // No such method
            throw new NoSuchMethodException(PhpXmlRpc::$xmlrpcstr['unknown_method'], PhpXmlRpc::$xmlrpcerr['unknown_method']);
        }

        // alter on-the-fly the config of the json parser if needed
        if (isset($dmap[$methodName]['parameters_type']) &&
            $dmap[$methodName]['parameters_type'] != $this->functions_parameters_type) {
            $xmlParser->forceReturnType($dmap[$methodName]['parameters_type']);
        }
    }

    /**
     * @return array[]
     *
     * @todo if building json-rpc-only webservers, you should at least undeclare the xml-rpc capability:
     *        unset($outAr['xmlrpc']);
     */
    public function getCapabilities()
    {
        $outAr = parent::getCapabilities();

        $outAr['json-rpc-2'] = new Value(
            array(
                'specUrl' => new Value('https://www.jsonrpc.org/specification', Value::$xmlrpcString),
                'specVersion' => new Value(2, Value::$xmlrpcInt)
            ),
            Value::$xmlrpcStruct
        );

        $outAr['json-rpc'] = new Value(
            array(
                'specUrl' => new Value('https://www.jsonrpc.org/specification_v1', Value::$xmlrpcString),
                'specVersion' => new Value(1, Value::$xmlrpcInt)
            ),
            Value::$xmlrpcStruct
        );

        if (isset($outAr['nil'])) {
            unset($outAr['nil']);
        }

        return $outAr;
    }

    /**
     * No xml header generated by the server, since we are sending json.
     * @deprecated this method was moved to the Response class
     *
     * @param string $charsetEncoding
     * @return string
     */
    protected function xml_header($charsetEncoding = '')
    {
        return '';
    }

    /**
     * @param string $methName
     * @return bool
     */
    protected function isSyscall($methName)
    {
        return (strpos($methName, "system.") === 0 || strpos($methName, "rpc.") === 0);
    }

    /**
     * @internal this function will become protected in the future
     *
     * @param Server $server
     * @param Value $call
     * @return Value
     */
    public static function _xmlrpcs_multicall_do_call($server, $call)
    {
        if ($call->kindOf() != 'struct') {
            return static::_xmlrpcs_multicall_error('notstruct');
        }
        $methName = @$call['method'];
        if (!$methName) {
            return static::_xmlrpcs_multicall_error('nomethod');
        }
        if ($methName->kindOf() != 'scalar' || $methName->scalarTyp() != 'string') {
            return static::_xmlrpcs_multicall_error('notstring');
        }
        if ($methName->scalarVal() == 'system.multicall') {
            return static::_xmlrpcs_multicall_error('recursion');
        }
        $params = @$call['params'];
        if (!$params) {
            //return static::_xmlrpcs_multicall_error('noparams');
            $params = new Value(array(), 'array');
        }
        $pk = $params->kindOf();
        if ($pk != 'array' && $pk != 'struct') {
            return static::_xmlrpcs_multicall_error('notarray');
        }

        $req = new Request($methName->scalarVal());
        foreach ($params as $i => $param) {
            // allow support for named parameters, as this is a jsonrpc 2.0 call (which it should, unless it is a system.multicall on 1.0...)
            if (!$req->addParam($param, $pk == 'struct' ? $i : null)) {
                if ($pk != 'struct') {
                    // for error message, we count params from 1
                    $i++;
                }
                return static::_xmlrpcs_multicall_error(new static::$responseClass(0,
                    PhpXmlRpc::$xmlrpcerr['incorrect_params'],
                    PhpXmlRpc::$xmlrpcstr['incorrect_params'] . ": probable json error in param " . $i));
            }
        }

        $result = $server->execute($req);

        if ($result->faultCode() != 0) {
            return static::_xmlrpcs_multicall_error($result); // Method returned fault.
        }

        return new Value(array($result->value()), 'array');
    }

    /**
     * @internal this function will become protected in the future
     *
     * @param Server $server
     * @param Value $call
     * @return Value
     */
    public static function _xmlrpcs_multicall_do_call_phpvals($server, $call)
    {
        if (!is_array($call)) {
            return static::_xmlrpcs_multicall_error('notstruct');
        }
        if (!array_key_exists('methodName', $call)) {
            return static::_xmlrpcs_multicall_error('nomethod');
        }
        if (!is_string($call['method'])) {
            return static::_xmlrpcs_multicall_error('notstring');
        }
        if ($call['method'] == 'system.multicall') {
            return static::_xmlrpcs_multicall_error('recursion');
        }
        if (!array_key_exists('params', $call)) {
            //return static::_xmlrpcs_multicall_error('noparams');
            $call['params'] = array();
        }
        if (!is_array($call['params'])) {
            return static::_xmlrpcs_multicall_error('notarray');
        }

        // this is a simplistic hack, since we might have received
        // base64 or datetime values, but they will be listed as strings here...
        $pt = array();
        $wrapper = new Wrapper();
        foreach ($call['params'] as $val) {
            // support EPI-encoded base64 and datetime values
            if ($val instanceof \stdClass && isset($val->xmlrpc_type)) {
                $pt[] = $val->xmlrpc_type == 'datetime' ? Value::$xmlrpcDateTime : $val->xmlrpc_type;
            } else {
                $pt[] = $wrapper->php2XmlrpcType(gettype($val));
            }
        }

        $result = $server->execute($call['methodName'], $call['params'], $pt);

        if ($result->faultCode() != 0) {
            return static::_xmlrpcs_multicall_error($result); // Method returned fault.
        }

        return new Value(array($result->value()), 'array');
    }

    /**
     * @internal this function will become protected in the future
     *
     * @param $err
     * @return Value
     */
    public static function _xmlrpcs_multicall_error($err)
    {
        if (is_string($err)) {
            $str = PhpXmlRpc::$xmlrpcstr["multicall_{$err}"];
            $code = PhpXmlRpc::$xmlrpcerr["multicall_{$err}"];
        } else {
            $code = $err->faultCode();
            $str = $err->faultString();
        }
        $struct = array();
        $struct['faultCode'] = new Value($code, 'int');
        $struct['faultString'] = new Value($str, 'string');

        return new Value($struct, 'struct');
    }

    public function getOption($name)
    {
        switch ($name) {
            case self::OPT_DEBUG_FORMAT:
                return $this->$name;
            default:
                return parent::getOption($name);
        }
    }

    public function setOption($name, $value)
    {
        switch ($name) {
            case self::OPT_DEBUG_FORMAT:
                $this->$name = $value;
                break;
            default:
                return parent::setOption($name, $value);
        }
    }
}
