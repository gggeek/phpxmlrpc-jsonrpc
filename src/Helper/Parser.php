<?php

namespace PhpXmlRpc\JsonRpc\Helper;

use PhpXmlRpc\JsonRpc\Encoder;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Response;
use PhpXmlRpc\JsonRpc\Value;
use PhpXmlRpc\PhpXmlRpc;

/**
 * @see https://www.jsonrpc.org/specification_v1
 * @todo add support for __jsonclass__
 * @todo add support for json-rpc 2.0 - see https://www.jsonrpc.org/specification
 * @todo add support for json-rpc 1.1 ? see: https://jsonrpc.org/historical/json-rpc-1-1-wd.html and
 *       https://jsonrpc.org/historical/json-rpc-1-1-alt.html
 *
 * @todo add a ParseValue method ?
 * @todo add a Parse method (same as XMLParse) ?
 * @todo add a guessEncoding function which is similar to the one in XMLParser but obeys better to rfc8259 (paragraph 8.1)
 */
class Parser
{
    /**
     * @see PhpXmlRpc/XMLParser
     */
    public $_xh = array(
        'isf' => 0,
        'isf_reason' => '',
        'value' => null,
        'method' => false,
        'params' => array(),
        'pt' => array(),
        'id' => null,
    );

    protected static $encoder;

    public function getEncoder()
    {
        if (self::$encoder === null) {
            self::$encoder = new Encoder();
        }
        return self::$encoder;
    }

    public function setencoder($encoder)
    {
        self::$encoder = $encoder;
    }

    /**
     * Parse a json string, expected to be in jsonrpc request format
     * @param $data
     * @param bool $returnPhpvals
     * @param string $srcEncoding
     *
     * @return bool
     *
     * @todo checks missing:
     *       - no extra members in request
     */
    public function parseRequest($data, $returnPhpvals = false, $srcEncoding = '')
    {
        $this->_xh = array(
            'isf' => 0,
            'isf_reason' => '',
            'value' => null,
            'method' => false,
            'params' => array(),
            'pt' => array(),
            'id' => null,
        );

        $ok = json_decode($data, true, PhpJsonRpc::$json_decode_depth, PhpJsonRpc::$json_decode_flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // error 3: json parsing fault, 2: invalid jsonrpc
            $this->_xh['isf'] = 3;
            $this->_xh['isf_reason'] = 'JSON parsing failed. error: ' . json_last_error();
            return false;
        }

        if (!is_array($ok) || !array_key_exists('method', $ok) || !is_string($ok['method']) ||
            !array_key_exists('params', $ok) || !is_array($ok['params']) ||
            !array_key_exists('id', $ok)
        ) {
            $this->_xh['isf'] = 2;
            $this->_xh['isf_reason'] = 'JSON parsing did not return correct jsonrpc 1.0 request object';
            return false;
        }

        if ($returnPhpvals) {
            // to allow 'phpvals' type servers to work, we need to rebuild $this->_xh['pt'] too
            foreach ($ok['params'] as $val) {
                $typ = gettype($val);
                if ($typ == 'array' && count($val) && count(array_diff_key($val, array_fill(0, count($val), null))) !== 0) {
                    $typ = 'object';
                }
                $this->_xh['pt'][] = $this->php2JsonrpcType($typ);
            }
        } else {
            foreach ($ok['params'] as &$val) {
                /// @todo what should be the default encoding options ?
                $val = $this->getEncoder()->encode($val);
            }

            /// @todo should we encode Id as well ?
        }

        $this->_xh['method'] = $ok['method'];
        $this->_xh['params'] = $ok['params'];
        $this->_xh['id'] = $ok['id'];

        return true;
    }

    /**
     * Parse a json string, expected to be in jsonrpc response format.
     * @todo checks missing:
     *       - no extra members in response
     * @param $data
     * @param bool $returnPhpvals
     * @param string $srcEncoding
     *
     * @return bool
     */
    public function parseResponse($data, $returnPhpvals = false, $srcEncoding = '')
    {
        $this->_xh = array(
            'isf' => 0,
            'isf_reason' => '',
            'value' => null,
            'method' => false,
            'params' => array(),
            'pt' => array(),
            'id' => null,
        );

        $ok = json_decode($data, true, PhpJsonRpc::$json_decode_depth, PhpJsonRpc::$json_decode_flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // error 3: json parsing fault, 2: invalid jsonrpc
            $this->_xh['isf'] = 3;
            $this->_xh['isf_reason'] = 'JSON parsing failed. error: ' . json_last_error();
            return false;
        }

        if (!is_array($ok) || !array_key_exists('result', $ok) || !array_key_exists('error', $ok) || !array_key_exists('id', $ok)
            || !($ok['error'] === null xor $ok['result'] === null)
        ) {
            $this->_xh['isf'] = 2;
            $this->_xh['isf_reason'] = 'JSON parsing did not return correct jsonrpc 1.0 response object';
            return false;
        }

        if (!$returnPhpvals) {
            $encoder = $this->getEncoder();
            /// @todo what should be the default encoding options ?
            if ($ok['error'] === null ) {
                $ok['result'] = $encoder->encode($ok['result']);
            }
        }

        if ($ok['error'] !== null) {
            $this->_xh['isf'] = 1;

            if (is_array($ok['error']) && array_key_exists('faultCode', $ok['error'])
                && array_key_exists('faultString', $ok['error'])
            ) {
                if ($ok['error']['faultCode'] == 0) {
                    // FAULT returned, errno needs to reflect that
                    /// @todo use a constant for this error code
                    $ok['error']['faultCode'] = -1;
                }
                $this->_xh['value'] = $ok['error'];
            }
            /// @todo what about jsonrpc servers that do NOT respect the faultCode/faultString convention?
            //        ATM we force the error into a string, except for ints and strings...
            else
            {
                $this->_xh['value'] = array(
                    /// @todo use a constant for this error code
                    'faultCode' => -1,
                    'faultString' => (is_string($ok['error']) || is_int($ok['error'])) ? $ok['error'] : var_export($ok['error'], true),
                    'error' => $ok['error'],
                );
            }

        } else {
            $this->_xh['value'] = $ok['result'];
        }

        /// @todo should we encode Id as well ?
        $this->_xh['id'] = $ok['id'];

        return true;
    }

    /**
     * Convert the json representation of a jsonrpc method call, jsonrpc method response
     * or single json value into the appropriate object (a.k.a. deserialize).
     * Please note that there is no way to distinguish the serialized representation
     * of a single json val of type object which has the 3 appropriate members from
     * the serialization of a method call or method response.
     * In such a case, the function will return a jsonrpcresp or jsonrpcmsg
     * @param string $jsonVal
     * @param array $options includes src_encoding, dest_encoding
     * @return Request|Response|Value|false false on error, or an instance of jsonrpcval, jsonrpcresp or jsonrpcmsg
     */
    public function decodeJson($jsonVal, $options = array())
    {
        $src_encoding = array_key_exists('src_encoding', $options) ? $options['src_encoding'] : PhpXmlRpc::$xmlrpc_defencoding;
        $dest_encoding = array_key_exists('dest_encoding', $options) ? $options['dest_encoding'] : PhpXmlRpc::$xmlrpc_internalencoding;

        $this->_xh = array(
            'isf' => 0,
            'isf_reason' => '',
            'value' => null,
            'method' => false,
            'params' => array(),
            'pt' => array(),
            'id' => null,
        );

        $ok = json_decode($jsonVal, true, PhpJsonRpc::$json_decode_depth, PhpJsonRpc::$json_decode_flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // error 3: json parsing fault, 2: invalid jsonrpc
            $this->_xh['isf'] = (json_last_error() !== JSON_ERROR_NONE) ? 3 : 2;
            $this->_xh['isf_reason'] = 'JSON parsing failed. error: ' . json_last_error();

            error_log($this->_xh['isf_reason']);
            return false;
        }

        $encoder = $this->getEncoder();

        if (is_array($ok) && array_key_exists('method', $ok) && array_key_exists('params', $ok) && array_key_exists('id', $ok) &&
            is_string($ok['method']) && is_array($ok['params'])) {
            $msg = new Request($ok['method'], array(), $ok['id']);
            foreach ($ok['params'] as $param) {
                $msg->addparam($encoder->encode($param, $options));
            }
            return $msg;
        }

        if (is_array($ok) && array_key_exists('result', $ok) && array_key_exists('error', $ok) && array_key_exists('id', $ok)) {
            if ($ok['error'] !== null) {
                $resp = new Response($encoder->encode($ok['result']));
            } else {
                if (is_array($ok['error']) && array_key_exists('faultCode', $ok['error'])
                    && array_key_exists('faultString', $ok['error'])
                ) {
                    $err = $ok['error'];
                    if ($err['faultCode'] == 0) {
                        // FAULT returned, errno needs to reflect that
                        /// @todo use a constant for this error code
                        $err['faultCode'] = -1;
                    }
                }
                // NB: what about jsonrpc servers that do NOT respect
                // the faultCode/faultString convention???
                // we force the error into a string. regardless of type...
                else
                {
                    /// @todo use a constant for this error code
                    $err = array('faultCode' => -1, 'faultString' => (is_string($ok['error']) || is_int($ok['error'])) ? $ok['error'] : var_export($ok['error'], true));
                }
                $resp = new Response(0, $err['faultCode'], $err['faultString']);
            }
            $resp->id = $ok['id'];
            return $resp;
        }
    }

    /**
     * Given a string defining a php type or phpxmlrpc type (loosely defined: strings
     * accepted come from javadoc blocks), return corresponding phpxmlrpc type.
     * NB: for php 'resource' types returns empty string, since resources cannot be serialized;
     * for php class names returns 'struct', since php objects can be serialized as json structs;
     * for php arrays always return 'array', even though arrays sometimes serialize as json structs
     * @param string $phpType
     * @return string
     */
    public function php2JsonrpcType($phpType)
    {
        switch (strtolower($phpType)) {
            case 'string':
                return Value::$xmlrpcString;
            case 'integer':
            case Value::$xmlrpcInt: // 'int'
            case Value::$xmlrpcI4:
                return Value::$xmlrpcInt;
            case 'double':
                return Value::$xmlrpcDouble;
            case 'boolean':
                return Value::$xmlrpcBoolean;
            case 'array':
                return Value::$xmlrpcArray;
            case 'object':
                return Value::$xmlrpcStruct;
            //case Value::$xmlrpcBase64:
            case Value::$xmlrpcStruct:
                return strtolower($phpType);
            case 'resource':
                return '';
            default:
                if (class_exists($phpType)) {
                    return Value::$xmlrpcStruct;
                } else {
                    // unknown: might be any 'extended' jsonrpc type
                    return Value::$xmlrpcValue;
                }
        }
    }
}
