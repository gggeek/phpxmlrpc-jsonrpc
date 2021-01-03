<?php


namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\Server as BaseServer;

/**
 * @todo implement dispatching of multicall requests, json way
 * @todo test system.XXX methods, with special care to multicall
 * @todo support for 'ping' calls, i.e. if id is null, echo back nothing
 */
class Server extends BaseServer
{
    //public $allow_system_funcs = false;
    public $functions_parameters_type = 'jsonrpcvals';

    public function serializeDebug($charsetEncoding = '')
    {
        $out = '';
        if ($this->debug_info != '') {
            $out .= "/* SERVER DEBUG INFO (BASE64 ENCODED):\n" . base64_encode($this->debug_info) . "\n*/\n";
        }
        if ($GLOBALS['_xmlrpc_debuginfo'] != '') {
            $out .= "/* DEBUG INFO:\n\n" . Charset::instance()->encodeEntities($GLOBALS['_xmlrpc_debuginfo'], null, $charsetEncoding) . "\n*/\n";
        }
        return $out;
    }

    /**
     * Note: syntax differs from overridden method, by adding an ID param
     * @access protected
     * @param mixed $m
     * @param null $params
     * @param null $paramtypes
     * @param null $msgID
     * @return false|\jsonrpcresp|mixed|Response|\PhpXmlRpc\Response|\xmlrpcresp
     * @throws \Exception
     */
    function execute($m, $params = null, $paramtypes = null, $msgID = null)
    {
        if (is_object($m)) {
            // watch out: if $m is an xmlrpcmsg obj, this will raise a warning: no id member...
            $methName = $m->method();
            $msgID = $m->id;
        } else {
            $methName = $m;
        }
        $sysCall = $this->isSyscall($methName);
        $dmap = $sysCall ? $GLOBALS['_xmlrpcs_dmap'] : $this->getSystemDispatchMap();

        if (!isset($dmap[$methName]['function'])) {
            // No such method
            return new Response(0,
                $GLOBALS['xmlrpcerr']['unknown_method'],
                $GLOBALS['xmlrpcstr']['unknown_method']);
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
                return new Response(
                    0,
                    $GLOBALS['xmlrpcerr']['incorrect_params'],
                    $GLOBALS['xmlrpcstr']['incorrect_params'] . ": ${errstr}"
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
            return new Response(
                0,
                $GLOBALS['xmlrpcerr']['server_error'],
                $GLOBALS['xmlrpcstr']['server_error'] . ": no function matches method"
            );
        }

        // If debug level is 3, we should catch all errors generated during
        // processing of user function, and log them as part of response
        if ($this->debug > 2) {
            $GLOBALS['_xmlrpcs_prev_ehandler'] = set_error_handler('_xmlrpcs_errorHandler');
        }
        try {
            if (is_object($m)) {
                if ($sysCall) {
                    $r = call_user_func($func, $this, $m);
                } else {
                    $r = call_user_func($func, $m);
                }
                if (!is_a($r, 'xmlrpcresp')) {
                    error_log("XML-RPC: " . __METHOD__ . ": function $func registered as method handler does not return an xmlrpcresp object");
                    if (is_a($r, 'xmlrpcval')) {
                        $r = new Response($r);
                    } else {
                        $r = new Response(
                            0,
                            $GLOBALS['xmlrpcerr']['server_error'],
                            $GLOBALS['xmlrpcstr']['server_error'] . ": function does not return jsonrpcresp or xmlrpcresp object"
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
                            $r = new Response(php_xmlrpc_encode($r, array('extension_api')));
                        }
                    } else {
                        $r = call_user_func_array($func, $params);
                    }
                }
                // the return type can be either an xmlrpcresp object or a plain php value...
                if (!is_a($r, 'xmlrpcresp')) {
                    // what should we assume here about automatic encoding of datetimes
                    // and php classes instances???
                    $r = new Response(php_jsonrpc_encode($r, $this->phpvals_encoding_options));
                }
            }
            // here $r is either an xmlrpcresp or jsonrpcresp
            if (!is_a($r, 'jsonrpcresp')) {

                // dirty trick: user has given us back an xmlrpc response,
                // since he had an existing xmlrpc server with boatloads of code.
                // Be nice to him, and serialize the xmlrpc stuff into JSON.
                // We also override the content_type of the xmlrpc response,
                // but lack knowledge of intended response charset...
                $r->content_type = 'application/json';
                $r->payload = serialize_jsonrpcresp($r, $msgID);
            } else {
                $r->id = $msgID;
            }
        } catch (\Exception $e) {
            // (barring errors in the lib) an uncatched exception happened
            // in the called function, we wrap it in a proper error-response
            switch ($this->exception_handling) {
                case 2:
                    throw $e;
                    break;
                case 1:
                    $r = new Response(0, $e->getCode(), $e->getMessage());
                    break;
                default:
                    $r = new Response(0, $GLOBALS['xmlrpcerr']['server_error'], $GLOBALS['xmlrpcstr']['server_error']);
            }
        }
        if ($this->debug > 2) {
            // note: restore the error handler we found before calling the
            // user func, even if it has been changed inside the func itself
            if ($GLOBALS['_xmlrpcs_prev_ehandler']) {
                set_error_handler($GLOBALS['_xmlrpcs_prev_ehandler']);
            } else {
                restore_error_handler();
            }
        }
        return $r;
    }

    /**
     * @param string $data
     * @param string $reqEncoding
     * @return false|\jsonrpcresp|mixed|jsonrpcresp|Request|\PhpXmlRpc\Response|\xmlrpcresp
     * @access protected
     */
    function parseRequest($data, $reqEncoding = '')
    {
        $GLOBALS['_xh'] = array();

        if (!jsonrpc_parse_req($data, $this->functions_parameters_type == 'phpvals' || $this->functions_parameters_type == 'epivals', false, $reqEncoding)) {
            $r = new Request(0,
                $GLOBALS['xmlrpcerr']['invalid_request'],
                $GLOBALS['xmlrpcstr']['invalid_request'] . ' ' . $GLOBALS['_xh']['isf_reason']);
        } else {
            if ($this->functions_parameters_type == 'phpvals' || $this->functions_parameters_type == 'epivals' ||
                (isset($this->dmap[$GLOBALS['_xh']['method']]['parameters_type']) && ($this->dmap[$GLOBALS['_xh']['method']]['parameters_type'] == 'phpvals'))
            ) {
                if ($this->debug > 1) {
                    $this->debugmsg("\n+++PARSED+++\n" . var_export($GLOBALS['_xh']['params'], true) . "\n+++END+++");
                }
                $r = $this->execute($GLOBALS['_xh']['method'], $GLOBALS['_xh']['params'], $GLOBALS['_xh']['pt'], $GLOBALS['_xh']['id']);
            } else {
                // build an xmlrpcmsg object with data parsed from xml
                $m = new Request($GLOBALS['_xh']['method'], 0, $GLOBALS['_xh']['id']);
                // now add parameters in
                /// @todo for more speed, we could just substitute the array...
                for ($i = 0; $i < sizeof($GLOBALS['_xh']['params']); $i++) {
                    $m->addParam($GLOBALS['_xh']['params'][$i]);
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
     * @access protected
     */
    protected function xml_header($charsetEncoding = '')
    {
        return '';
    }

    /**
     * @return array[]
     * @todo if building jsonrpc-only webservers, you should at least undeclare the xmlrpc capability:
     *        unset($outAr['xmlrpc']);
     *        Also,
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
