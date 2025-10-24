<?php

namespace PhpXmlRpc\JsonRpc;

use PhpXmlRpc\Client as BaseClient;
use PhpXmlRpc\Exception\ValueErrorException;
use PhpXmlRpc\Helper\Interop;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Traits\JsonRpcVersionAware;
use PhpXmlRpc\PhpXmlRpc;

/**
 * @todo the JSON proposed RFC states that when making json calls, we should specify an 'accept: application/json'
 *       http header. Currently, we either do not output an 'accept' header or specify 'any' (in curl mode)
 */
class Client extends BaseClient
{
    use JsonRpcVersionAware;

    protected static $requestClass = '\\PhpXmlRpc\\JsonRpc\\Request';
    protected static $responseClass = '\\PhpXmlRpc\\JsonRpc\\Response';

    const OPT_JSONRPC_VERSION = 'jsonrpc_version';

    protected static $extra_options = array(
        self::OPT_JSONRPC_VERSION,
    );

    // multicall exists for JSON-RPC 2.0, but not 1.0, so we add NULL as 3rd state
    protected $no_multicall = null;

    // default return type of calls to json-rpc servers: jsonrpcvals
    public $return_type = Parser::RETURN_JSONRPCVALS;

    // according to https://tools.ietf.org/html/rfc8259#section-8.1, UTF8 is the rule
    public $request_charset_encoding = 'UTF-8';

    public function __construct($path, $server='', $port='', $method='')
    {
        parent::__construct($path, $server, $port, $method);

        // @todo we need to override the list of std supported encodings, since according to ECMA-262, the standard
        //       charset is UTF-16...
        //$this->accepted_charset_encodings = array('UTF-16', 'UTF-8', 'ISO-8859-1', 'US-ASCII');

        $this->user_agent =  PhpJsonRpc::$jsonrpcName . ' ' . PhpJsonRpc::$jsonrpcVersion . ' (' . PhpXmlRpc::$xmlrpcName . ' ' . PhpXmlRpc::$xmlrpcVersion . ')';
    }

    public function setOption($name, $value)
    {
        if (in_array($name, static::$options) || in_array($name, static::$extra_options)) {
            $this->$name = $value;
            return $this;
        }

        throw new ValueErrorException("Unsupported option '$name'");
    }

    /**
     * @param string $name see all the OPT_ constants
     * @return mixed
     * @throws ValueErrorException on unsupported option
     */
    public function getOption($name)
    {
        if (in_array($name, static::$options) || in_array($name, static::$extra_options)) {
            return $this->$name;
        }

        throw new ValueErrorException("Unsupported option '$name'");
    }

    /**
     * @param \PhpXmlRpc\JsonRpc\Request|\PhpXmlRpc\JsonRpc\Request[]|string $req NB: when sending an empty array, an
     *        empty array is returned, even though, according to the spec, an error response is returned by the
     *        server
     * @param int $timeout deprecated
     * @param string $method deprecated
     * @return \PhpXmlRpc\JsonRpc\Response|true|\PhpXmlRpc\JsonRpc\Response[] true for notification calls, if the server
     *         returns an empty http response body
     */
    public function send($req, $timeout = 0, $method = '')
    {
        $originalMulticall = $this->no_multicall;
        $jsonrpcVersion = null;
        if ($this->jsonrpc_version != '') {
            // force the json-rpc version onto all requests
            if (is_array($req)) {
                foreach ($req as $i => $r) {
                    if (!is_string($r)) {
                        $req[$i]->setJsonRpcVersion($this->jsonrpc_version);
                    }
                }
            } elseif (!is_string($req)) {
                $req->setJsonRpcVersion($this->jsonrpc_version);
            }

            $jsonrpcVersion = $this->jsonrpc_version;
        } else {
            // check: this might be handled as a batch call, if we have an array of requests and all are json-rpc 2.0
            if (is_array($req) && $this->no_multicall === null) {
                $jsonrpcVersions = array();
                foreach ($req as $i => $r) {
                    if (!is_string($r)) {
                        $jsonrpcVersions[$i] = $r->getJsonRpcVersion();
                        if ($jsonrpcVersions[$i] == null) {
                            $jsonrpcVersions[$i] = PhpJsonRpc::$defaultJsonrpcVersion;
                        }
                    }
                }
                $jsonrpcVersions = array_unique($jsonrpcVersions);
                if (count($jsonrpcVersions) == 1) {
                    $jsonrpcVersion = reset($jsonrpcVersions);
                }
            }
        }

        if (is_array($req) && $this->no_multicall === null) {
            if ($jsonrpcVersion === PhpJsonRpc::VERSION_2_0) {
                $this->no_multicall = false;

                /// @todo since batch calling is part of the spec, we should disable falling back to many single calls

            } else {
                // there is no batch calling nor system.multicall in json-rpc 1.0 - it is something only we provide
                $this->no_multicall = true;
            }
        }

        /** @var Response $resp */
        $resp = parent::send($req, $timeout, $method);

        // For all cases where the response is not sent from the server, but generated by the client/request, we have to
        // inject the req. Id and jsonrpc version
        if (is_array($req)) {
            foreach ($req as $i => $r) {
                /// @todo test: does this work when notifications are mixed with calls?
                if (!is_string($r)) {
                    if (!is_bool($resp[$i]) && !$r->parsedResponseIsFromServer()) {
                        if ($r->id() !== null) {
                            $resp[$i] = call_user_func_array(array(static::$responseClass, 'withId'),
                                array($resp[$i], $r->id()));
                        }
                        if ($this->jsonrpc_version !== null) {
                            $resp[$i]->setJsonRpcVersion($this->jsonrpc_version);
                        }
                        $this->fixErrorCodeIfNeeded($resp[$i]);
                    }
                    // fix the following corner case: the client sends twice the same request object, and on the 2nd try
                    // it returns an error resp. without going through $req->parseResponse(). In that case,
                    // $req->parsedResponseIsFromServer() would return true when though it should not. So we reset it
                    $r->resetParsedResponseIsFromServer();
                }
            }
        } elseif (!is_string($req)) {
            if (!is_bool($resp) && !$req->parsedResponseIsFromServer()) {
                if ($req->id() !== null) {
                    $resp = call_user_func_array(array(static::$responseClass, 'withId'),
                        array($resp, $req->id()));
                }
                if ($this->jsonrpc_version !== null) {
                    $resp->setJsonRpcVersion($this->jsonrpc_version);
                }
                $this->fixErrorCodeIfNeeded($resp);
            }
            $req->resetParsedResponseIsFromServer();
        }

        if (is_array($req) && $originalMulticall === null) {
            /// @todo we should skip resetting it back to null if it was set to false because server does not support
            ///       batch calls
            $this->no_multicall = null;
        }

        return $resp;
    }

    /**
     * @param Response $resp
     * @return void
     */
    protected function fixErrorCodeIfNeeded($resp)
    {
        // Jsonrpc 2.0 responses use the same error codes as the phpxmlrpc interop ones.
        // We fix them without changing the global error codes, in case there are some xml-rpc calls being answered, too
        if (($errCode = $resp->faultCode()) != 0 && $resp->getJsonRpcVersion() === PhpJsonRpc::VERSION_2_0) {
            $errKeys = array_flip(PhpXmlRpc::$xmlrpcerr);
            if (isset($errKeys[$errCode]) && isset(Interop::$xmlrpcerr[$errKeys[$errCode]])) {
                /// @todo do not use deprecated property accessor to set this value
                $resp->errno = Interop::$xmlrpcerr[$errKeys[$errCode]];
            }
        }
    }

    /**
     * Attempt to boxcar $reqs via a batch call.
     *
     * @param Request[] $reqs
     * @param int $timeout
     * @param string $method
     * @return Response[]|Response a single Response when the call returned a fault / does not conform to what we expect
     *                             from a multicall response
     */
    protected function _try_multicall($reqs, $timeout, $method)
    {
        if (!$reqs) {
            $payload = '[]';
        } else {
            $payload = array();
            foreach ($reqs as $req) {
                $payload[] = $req->serialize($this->request_charset_encoding);
            }
            $payload = "[\n" . implode(",\n", $payload) . "\n]";
        }

        $result = parent::send($payload);

        if ($result->faultCode() != 0) {
            // batch call failed
            return $result;
        }

        // Unpack responses.
        $rets = $result->value();
        $response = array();

        if ($this->return_type == 'xml' || $this->return_type == 'json') {
            for ($i = 0; $i < count($reqs); $i++) {
                /// @todo can we do better? we set the complete json into each response...
                $response[] = new static::$responseClass($rets, 0, '', 'json', null, $result->httpResponse());
            }

        } elseif ($this->return_type == 'phpvals') {
            if (!is_array($rets)) {
                // bad return type
                return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                    PhpXmlRpc::$xmlrpcstr['multicall_error'] . ': not an array', 'phpvals', null, $result->httpResponse());
            }
            $numRets = count($rets);
            if ($numRets > count($reqs)) {
                // wrong number of return values.
                return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                    PhpXmlRpc::$xmlrpcstr['multicall_error'] . ': incorrect number of responses', 'phpvals', null,
                    $result->httpResponse());
            }

            for ($i = 0; $i < $numRets; $i++) {
                $val = $rets[$i];
                if (!is_array($val)) {
                    return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                        PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i is not an array or struct",
                        'phpvals', null, $result->httpResponse());
                }
                if (array_key_exists('result', $val)) {
                    // Normal return value
                    $response[$i] = new static::$responseClass($val['result'], 0, '', 'phpvals', null, $result->httpResponse());
                } elseif (array_key_exists('error', $val)) {
                    /// @todo remove usage of @: it is apparently quite slow
                    $code = @$val['error']['code'];
                    if (!is_int($code)) {
                        /// @todo should we check that it is != 0?
                        return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                            PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has invalid or no error code",
                            'phpvals', null, $result->httpResponse());
                    }
                    $str = @$val['error']['message'];
                    if (!is_string($str)) {
                        return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                            PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has invalid or no error message",
                            'phpvals', null, $result->httpResponse());
                    }
                    $response[$i] = new static::$responseClass(0, $code, $str, 'phpvals', $result->httpResponse());
                } else {
                    return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                        PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has neither result nor error",
                        'phpvals', null, $result->httpResponse());
                }
            }

        } else {
            // return type == 'jsonrpcvals'
            if ($rets->kindOf() != 'array') {
                return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                    PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element is not an array", 'xmlrpcvals',
                    null, $result->httpResponse());
            }
            $numRets = $rets->count();
            if ($numRets > count($reqs)) {
                // wrong number of return values.
                return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                    PhpXmlRpc::$xmlrpcstr['multicall_error'] . ': incorrect number of responses', 'xmlrpcvals', null,
                    $result->httpResponse());
            }

            foreach ($rets as $i => $val) {
                if ($val->kindOf() == 'struct') {
                    if (isset($val['result'])) {
                        $response[] = new static::$responseClass($val['result'], 0, '', 'jsonrpcvals', null, $result->httpResponse());
                    } elseif (isset($val['error'])) {
                        /** @var Value $code */
                        $code = @$val['error']['code'];
                        if (!$code || $code->kindOf() != 'scalar' || $code->scalarTyp() != 'int') {
                            return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                                PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has invalid or no code",
                                'xmlrpcvals', null, $result->httpResponse());
                        }
                        /** @var Value $str */
                        $str = @$val['error']['message'];
                        if (!$str || $str->kindOf() != 'scalar' || $str->scalarTyp() != 'string') {
                            return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                                PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has invalid or no message",
                                'xmlrpcvals', null, $result->httpResponse());
                        }
                        $response[] = new static::$responseClass(null, $code->scalarval(), $str->scalarval(),
                            'jsonrpcvals', null, $result->httpResponse());
                    } else {
                        return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                            PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i has neither result nor error",
                            'phpvals', null, $result->httpResponse());
                    }
                } else {
                    return new static::$responseClass(0, PhpXmlRpc::$xmlrpcerr['multicall_error'],
                        PhpXmlRpc::$xmlrpcstr['multicall_error'] . ": response element $i is not a struct",
                        'xmlrpcvals', null, $result->httpResponse());
                }
            }
        }

        return $response;
    }
}
