<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Server as BaseServer;

/**
 * @todo implement dispatching of multicall requests, json way
 * @todo test system.XXX methods, with special care to multicall
 * @todo support 'notification' calls, i.e. if id is null, echo back nothing
 */
class Server extends BaseServer
{
    //public $allow_system_funcs = false;
    public $functions_parameters_type = 'jsonrpcvals';

    protected static $encoder;
    protected static $parser;
    protected static $serializer;

    public function getEncoder()
    {
        if (self::$encoder === null) {
            self::$encoder = new Encoder();
        }
        return self::$encoder;
    }

    public static function setencoder($encoder)
    {
        self::$encoder = $encoder;
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
     * @todo allow to (optionally) send comments as top-level json element, since 99.99% of json parsers will barf on js comments...
     */
    public function serializeDebug($charsetEncoding = '')
    {
        $out = '';
        if ($this->debug_info != '') {
            $out .= "/* SERVER DEBUG INFO (BASE64 ENCODED):\n" . base64_encode($this->debug_info) . "\n*/\n";
        }
        if (static::$_xmlrpc_debuginfo != '') {
            // make sure the user's comments can not break the JS comment
            $out .= "/* DEBUG INFO:\n\n" . str_replace('*/', '*\u002f', Charset::instance()->encodeEntities(static::$_xmlrpc_debuginfo, PhpXmlRpc::$xmlrpc_internalencoding, $charsetEncoding) . "\n*/\n");
        }
        return $out;
    }

    /**
     * Note: syntax differs from overridden method, by adding an ID param
     * @param mixed $m
     * @param null $params
     * @param null $paramtypes
     * @param null $msgID
     *
     * @return Response|\PhpXmlRpc\Response
     *
     * @throws \Exception
     */
    protected function execute($m, $params = null, $paramtypes = null, $msgID = null)
    {
        if (is_object($m)) {
            // watch out: if $m is an xmlrpc request obj, this will raise a warning: no id member...
            $methName = $m->method();
            $msgID = $m->id;
        } else {
            $methName = $m;
        }
        $sysCall = $this->isSyscall($methName);
        $dmap = $sysCall ? $this->getSystemDispatchMap() : $this->dmap;

        if (!isset($dmap[$methName]['function'])) {
            // No such method
            return new Response(0, PhpXmlRpc::$xmlrpcerr['unknown_method'], PhpXmlRpc::$xmlrpcstr['unknown_method'], '', $msgID);
        }

        // Check signature
        if (isset($dmap[$methName]['signature'])) {
            $sig = $dmap[$methName]['signature'];
            if (is_object($m)) {
                list($ok, $errstr) = $this->verifySignature($m, $sig);
            } else {
                list($ok, $errstr) = $this->verifySignature($paramtypes, $sig);
            }
            if (!$ok) {
                // Didn't match.
                return new Response(0, PhpXmlRpc::$xmlrpcerr['incorrect_params'],
                    PhpXmlRpc::$xmlrpcstr['incorrect_params'] . ": ${errstr}", '', $msgID
                );
            }
        }

        $func = $dmap[$methName]['function'];
        // let the 'class::function' syntax be accepted in dispatch maps
        if (is_string($func) && strpos($func, '::')) {
            $func = explode('::', $func);
        }
        // verify that function to be invoked is in fact callable
        if (!is_callable($func)) {
            error_log("XML-RPC: " . __METHOD__ . ": function $func registered as method handler is not callable");
            return new Response(0, PhpXmlRpc::$xmlrpcerr['server_error'],
                PhpXmlRpc::$xmlrpcstr['server_error'] . ': no function matches method', '', $msgID
            );
        }

        // If debug level is 3, we should catch all errors generated during
        // processing of user function, and log them as part of response
        if ($this->debug > 2) {
            self::$_xmlrpcs_prev_ehandler = set_error_handler(array('\PhpXmlRpc\JsonRpc\Server', '_xmlrpcs_errorHandler'));
        }
        try {
            if (is_object($m)) {
                if ($sysCall) {
                    $r = call_user_func($func, $this, $m);
                } else {
                    $r = call_user_func($func, $m);
                }
                if (!is_a($r, 'PhpXmlRpc\Response')) {
                    error_log("XML-RPC: " . __METHOD__ . ": function $func registered as method handler does not return an xmlrpc response object");
                    if (is_a($r, 'PhpXmlRpc\Value')) {
                        $r = new Response($r);
                    } else {
                        $r = new Response(0, PhpXmlRpc::$xmlrpcerr['server_error'],
                            PhpXmlRpc::$xmlrpcstr['server_error'] . ": function does not return jsonrpc or xmlrpc response object"
                        );
                    }
                }
            } else {
                // call a 'plain php' function
                if ($sysCall) {
                    array_unshift($params, $this);
                    $r = call_user_func_array($func, $params);
                } else {
                    // 3rd API convention for method-handling functions: EPI-style
                    if ($this->functions_parameters_type == 'epivals') {
                        $r = call_user_func_array($func, array($methName, $params, $this->user_data));
                        // mimic EPI behaviour: if we get an array that looks like an error, make it
                        // an error response
                        if (is_array($r) && array_key_exists('faultCode', $r) && array_key_exists('faultString', $r)) {
                            $r = new Response(0, (integer)$r['faultCode'], (string)$r['faultString']);
                        } else {
                            // functions using EPI api should NOT return resp objects,
                            // so make sure we encode the return type correctly
                            $r = new Response($this->getEncoder()->encode($r, array('extension_api')));
                        }
                    } else {
                        $r = call_user_func_array($func, $params);
                    }
                }
                // the return type can be either a Response object or a plain php value...
                if (!is_a($r, 'PhpXmlRpc\Response')) {
                    // what should we assume here about automatic encoding of datetimes
                    // and php classes instances???
                    $r = new Response($this->getEncoder()->encode($r, $this->phpvals_encoding_options));
                }
            }
            // here $r is either an xmlrpcresp or jsonrpcresp
            if (!is_a($r, 'PhpXmlRpc\JsonRpc\Response')) {

                // dirty trick: user has given us back an xmlrpc response,
                // since he had an existing xmlrpc server with boatloads of code.
                // Be nice to him, and serialize the xmlrpc stuff into JSON.
                // We also override the content_type of the xmlrpc response,
                // but lack knowledge of intended response charset...
                $r->content_type = 'application/json';
                $r->payload = $this->getSerializer()->serializeResponse($r, $msgID);
            } else {
                $r->id = $msgID;
            }
        } catch (\Exception $e) {
            // (barring errors in the lib) an uncatched exception happened
            // in the called function, we wrap it in a proper error-response
            switch ($this->exception_handling) {
                case 2:
                    throw $e;
                case 1:
                    $r = new Response(0, $e->getCode(), $e->getMessage(), '', $msgID);
                    break;
                default:
                    $r = new Response(0, PhpXmlRpc::$xmlrpcerr['server_error'], PhpXmlRpc::$xmlrpcstr['server_error'], '', $msgID);
            }
        }
        if ($this->debug > 2) {
            // note: restore the error handler we found before calling the
            // user func, even if it has been changed inside the func itself
            if (self::$_xmlrpcs_prev_ehandler) {
                set_error_handler(self::$_xmlrpcs_prev_ehandler);
            } else {
                restore_error_handler();
            }
        }
        return $r;
    }

    /**
     * @param string $data
     * @param string $reqEncoding
     *
     * @return Response|\PhpXmlRpc\Response
     *
     * @throws \Exception
     *
     * @access protected
     */
    public function parseRequest($data, $reqEncoding = '')
    {
        $parser = $this->getParser();
        if (!$parser->parseRequest($data, $this->functions_parameters_type == 'phpvals' || $this->functions_parameters_type == 'epivals', $reqEncoding)) {
            $r = new Response(0,
                PhpXmlRpc::$xmlrpcerr['invalid_request'],
                PhpXmlRpc::$xmlrpcstr['invalid_request'] . ' ' . $parser->_xh['isf_reason']);
        } else {
            if ($this->functions_parameters_type == 'phpvals' || $this->functions_parameters_type == 'epivals' ||
                (isset($this->dmap[$parser->_xh['method']]['parameters_type']) &&
                ($this->dmap[$parser->_xh['method']]['parameters_type'] == 'phpvals' || $this->dmap[$parser->_xh['method']]['parameters_type'] == 'epivals'))
            ) {
                if ($this->debug > 1) {
                    $this->debugmsg("\n+++PARSED+++\n" . var_export($parser->_xh['params'], true) . "\n+++END+++");
                }
                $r = $this->execute($parser->_xh['method'], $parser->_xh['params'], $parser->_xh['pt'], $parser->_xh['id']);
            } else {
                // build an xmlrpcmsg object with data parsed from xml
                $m = new Request($parser->_xh['method'], array(), $parser->_xh['id']);
                // now add parameters in
                /// @todo for more speed, we could just substitute the array...
                for ($i = 0; $i < sizeof($parser->_xh['params']); $i++) {
                    $m->addParam($parser->_xh['params'][$i]);
                }

                if ($this->debug > 1) {
                    $this->debugmsg("\n+++PARSED+++\n" . var_export($m, true) . "\n+++END+++");
                }

                $r = $this->execute($m);
            }
        }
        return $r;
    }

    /**
     * No xml header generated by the server, since we are sending json
     * @param string $charsetEncoding
     * @return string
     */
    protected function xml_header($charsetEncoding = '')
    {
        return '';
    }

    /**
     * @return array[]
     * @todo if building jsonrpc-only webservers, you should at least undeclare the xmlrpc capability:
     *        unset($outAr['xmlrpc']);
     */
    public function getCapabilities()
    {
        $outAr = parent::getCapabilities();
        $outAr['json-rpc'] = new Value(array(
            'specUrl' => new Value('http://json-rpc.org/wiki/specification', Value::$xmlrpcString),
            'specVersion' => new Value(1, Value::$xmlrpcInt)
        ), Value::$xmlrpcStruct);
        if (isset($outAr['nil'])) {
            unset($outAr['nil']);
        }
        return $outAr;
    }
}
