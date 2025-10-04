<?php

namespace PhpXmlRpc\JsonRpc\Helper;

use PhpXmlRpc\Helper\Logger;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Response;
use PhpXmlRpc\JsonRpc\Traits\EncoderAware;
use PhpXmlRpc\JsonRpc\Value;
use PhpXmlRpc\Traits\LoggerAware;

/**
 * @see https://www.jsonrpc.org/specification_v1, https://www.jsonrpc.org/specification
 * @todo add support for __jsonclass__
 * @todo add support for json-rpc 1.1 ? see: https://jsonrpc.org/historical/json-rpc-1-1-wd.html and
 *       https://jsonrpc.org/historical/json-rpc-1-1-alt.html
 *
 * @todo add a ParseValue method ?
 * @todo add a Parse method (same as XMLParser) ?
 * @todo add a constructor which can be used to set default options
 */
class Parser
{
    use EncoderAware;
    use LoggerAware;

    const RETURN_JSONRPCVALS = 'jsonrpcvals';
    const RETURN_PHP = 'phpvals';

    /**
     * @see \PhpXmlRpc\XMLParser
     */
    public $_xh = array(
        // 3: json parsing fault, 2: invalid json-rpc, 1: fault response
        'isf' => 0,
        'isf_reason' => '',
        'value' => null,
        'method' => false,
        'params' => array(),
        'pt' => array(),
        'id' => null,
        'jsonrpc_version' => PhpJsonRpc::VERSION_1_0
    );

    protected $returnTypeOverride = null;

    /**
     * Parse a json string, expected to be in json-rpc request format.
     *
     * @param $data
     * @param string $returnType
     * @param array $options integer keys: options passed to the inner json parser
     *                       string keys:
     *                       - source_charset (string)
     *                       - target_charset (string)
     * @return false|array
     * @throws \Exception this can happen if a callback function is set and it does throw (i.e. we do not catch exceptions)
     *
     * @todo checks missing:
     *       - no extra members in request
     */
    public function parseRequest($data, $returnType = self::RETURN_JSONRPCVALS, $options = array())
    {
        // BC
        if (is_bool($returnType)) {
            $returnType = $returnType ? self::RETURN_PHP : self::RETURN_JSONRPCVALS;
        }
        if (is_string($options)) {
            $options = array('source_charset' => $options);
        }

        $ok = $this->jsonDecode($data, $options);
        if ($this->_xh['isf'] !== 0) {
            return false;
        }

        if (!is_array($ok)) {
            $this->_xh['isf'] = 2;
            $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc response object';
            return false;
        }
        if (array_key_exists('jsonrpc', $ok) && $ok['jsonrpc'] === '2.0') {
            if (!array_key_exists('method', $ok) || !is_string($ok['method']) ||
                (array_key_exists('params', $ok) && !is_array($ok['params'])) ||
                (array_key_exists('id', $ok) && is_array($ok['id']))
            ) {
                $this->_xh['isf'] = 2;
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc 2.0 request object';
                return false;
            }
        } else {
            if (!array_key_exists('method', $ok) || !is_string($ok['method']) ||
                !array_key_exists('params', $ok) || !is_array($ok['params']) ||
                !array_key_exists('id', $ok)
            ) {
                $this->_xh['isf'] = 2;
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc 1.0 request object';
                return false;
            }
        }

        $this->returnTypeOverride = null;
        if (isset($options['methodname_callback'])) {
            call_user_func($options['methodname_callback'], $ok['method'], $this);
        }
        if ($this->returnTypeOverride != '') {
            $returnType = $this->returnTypeOverride;
        }

        /// @todo handle unknown $returnType values, such as eg. epivals
        if ($returnType == self::RETURN_PHP) {
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
                /// @todo what should be the default encoding options?
                $val = $this->getEncoder()->encode($val);
            }

            /// @todo should we encode Id as well ?
        }

        $this->_xh['method'] = $ok['method'];
        $this->_xh['params'] = $ok['params'];
        $this->_xh['id'] = isset($ok['id']) ? $ok['id'] : null;
        if (isset($ok['jsonrpc'])) {
            $this->_xh['jsonrpc_version'] = $ok['jsonrpc'];
        }

        return $this->_xh;
    }

    public function forceReturnType($returnType)
    {
        $this->returnTypeOverride = $returnType;
    }

    /**
     * Parse a json string, expected to be in json-rpc response format.
     *
     * @param $data
     * @param string $returnType
     * @param array $options
     * @return false|array
     *
     * @todo checks missing:
     *       - no extra members in response
     */
    public function parseResponse($data, $returnType = self::RETURN_JSONRPCVALS, $options = array())
    {
        // BC
        if (is_bool($returnType)) {
            $returnType = $returnType ? self::RETURN_PHP : self::RETURN_JSONRPCVALS;
        }
        if (is_string($options)) {
            $options = array('source_charset' => $options);
        }

        $ok = $this->jsonDecode($data, $options);
        if ($this->_xh['isf'] !== 0) {
            return false;
        }

        if (!is_array($ok)) {
            $this->_xh['isf'] = 2;
            $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc response object';
            return false;
        }
        if (array_key_exists('jsonrpc', $ok) && $ok['jsonrpc'] === '2.0') {
            if (!array_key_exists('id', $ok) || (!array_key_exists('result', $ok) && !array_key_exists('error', $ok))
                || (array_key_exists('result', $ok) && array_key_exists('error', $ok)) ||
                (array_key_exists('error', $ok) && !is_array($ok['error']))
            ) {
                $this->_xh['isf'] = 2;
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc 2.0 response object';
                return false;
            }
        } else {
            if (!array_key_exists('id', $ok) || !array_key_exists('result', $ok) || !array_key_exists('error', $ok)
                || ($ok['error'] !== null && $ok['result'] !== null)
            ) {
                $this->_xh['isf'] = 2;
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct json-rpc 1.0 response object';
                return false;
            }
        }

        if ($returnType != self::RETURN_PHP) {
            $encoder = $this->getEncoder();
            /// @todo what should be the default encoding options?
            if (!isset($ok['error']) || $ok['error'] === null) {
                $ok['result'] = $encoder->encode($ok['result']);
            }
        }

        if (isset($ok['error']) && $ok['error'] !== null) {
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
            /// @todo what about json-rpc servers that do NOT respect the faultCode/faultString convention?
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
        $this->_xh['id'] = isset($ok['id']) ? $ok['id'] : null;

        if (isset($ok['jsonrpc'])) {
            $this->_xh['jsonrpc_version'] = $ok['jsonrpc'];
        }

        return $this->_xh;
    }

    /**
     * Convert the json representation of a json-rpc method call, json-rpc method response or single json value into the
     * appropriate object (a.k.a. deserialize).
     * Please note that there is no way to distinguish the serialized representation of a single json val of type object
     * which has the 3 appropriate members from the serialization of a method call or method response.
     * In such a case, the function will return a json-rpc Request or json-rpc Response
     *
     * @param string $jsonVal
     * @param array $options includes source_charset, target_charset
     * @return Request|Response|Value|false false on error, or an instance of Value, Response or Request
     */
    public function decodeJson($jsonVal, $options = array())
    {
        $ok = $this->jsonDecode($jsonVal, $options);
        if ($this->_xh['isf'] !== 0) {
            return false;
        }

        $encoder = $this->getEncoder();

        if (is_array($ok) && array_key_exists('method', $ok) && array_key_exists('params', $ok) && array_key_exists('id', $ok) &&
            is_string($ok['method']) && is_array($ok['params'])) {
            $req = new Request($ok['method'], array(), $ok['id']);
            foreach ($ok['params'] as $param) {
                $req->addparam($encoder->encode($param, $options));
            }
            return $req;
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
                // NB: what about json-rpc servers that do NOT respect
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
     * Given a string defining a php type or phpxmlrpc type (loosely defined: strings accepted come from phpdoc blocks),
     * return corresponding phpxmlrpc type.
     * NB: for php 'resource' types returns empty string, since resources cannot be serialized;
     * for php class names returns 'struct', since php objects can be serialized as json structs;
     * for php arrays always return 'array', even though arrays sometimes serialize as json structs
     *
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
                    // unknown: might be any 'extended' json-rpc type
                    return Value::$xmlrpcValue;
                }
        }
    }

    /**
     * Carries out the 'json-decoding' part of the parsing, including charset transcoding; resets $this->_xh; sets
     * $this->_xh['isf'] on errors.
     *
     * @param string $data
     * @param array $options
     * @return mixed
     */
    protected function jsonDecode($data, $options = array())
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

        $srcEncoding = isset($options['source_charset']) ? $options['source_charset'] : '';
        if (!in_array($srcEncoding, array('', 'UTF-8', 'US-ASCII'))) {
            if (function_exists('mb_convert_encoding')) {
                $data = mb_convert_encoding($data, 'UTF-8', $srcEncoding);
            } else {
                if ($srcEncoding == 'ISO-8859-1') {
                    $data = utf8_encode($data);
                } else {
                    $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': unsupported charset encoding of received data: ' . $srcEncoding);
                }
            }
        }

        $out = json_decode($data, true, PhpJsonRpc::$json_decode_depth, PhpJsonRpc::$json_decode_flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->_xh['isf'] = 3;
            $this->_xh['isf_reason'] = 'JSON parsing failed. Error: ' . json_last_error();
/// @todo check what the parent class does log
            $this->getLogger()->error($this->_xh['isf_reason']);
            //return false;
        }

        $dstEncoding = isset($options['target_charset']) ? $options['target_charset'] : '';
        if ($dstEncoding != '' && $dstEncoding != 'UTF-8')
        {
            if (function_exists('mb_convert_encoding')) {
                $this->convertEncoding($out, $dstEncoding);
            } else {
                if ($dstEncoding == 'ISO-8859-1') {
                    $this->convertEncoding($out, false);
                } else {
                    $this->getLogger()->error('JSON-RPC: ' . __METHOD__ . ': unsupported internal charset encoding of application: ' . $dstEncoding);
                }
            }
        }

        return $out;
    }

    /**
     * The relevant RFC is https://www.rfc-editor.org/rfc/rfc8259#section-8.1, which says we SHOULD always use UTF-8.
     * We opt instead to "respect" - but log as error - any charset declared via the content-type http header or the BOM...
     *
     * @param string $httpHeader
     * @param string $jsonChunk at least the first 4 bytes are required
     * @return string
     *
     * @todo should we return 'UTF-8' or '' by default?
     */
    public static function guessEncoding($httpHeader = '', $jsonChunk = '')
    {
        $errorMsg = '';
        $charset = 'UTF-8';

        // 1 - test if encoding is specified in HTTP HEADERS
        // Details:
        // LWS:           (\13\10)?( |\t)+
        // token:         (any char but excluded stuff)+
        // quoted string: " (any char but double quotes and control chars)* "
        // header:        Content-type = ...; charset=value(; ...)*
        //   where value is of type token, no LWS allowed between 'charset' and value
        // Note: we do not check for invalid chars in VALUE:
        //   this had better be done using pure ereg as below
        // Note 2: we might be removing whitespace/tabs that ought to be left in if
        //   the received charset is a quoted string. But nobody uses such charset names...
        /// @todo this test will pass if ANY header has charset specification, not only Content-Type. Fix it?
        $matches = array();
        if (preg_match('/;\s*charset\s*=([^;]+)/i', $httpHeader, $matches)) {
            $charset = strtoupper(trim($matches[1], " \t\""));
            if ($charset != 'UTF-8') {
                $errorMsg = "Received content in unexpected character set: $charset (declaration in content-type header)";
            }
        }

        // 2 - scan the first bytes of the data for a UTF-16 (or other) BOM pattern
        else if (preg_match('/^(?:\x00\x00\xFE\xFF|\xFF\xFE\x00\x00|\x00\x00\xFF\xFE|\xFE\xFF\x00\x00)/', $jsonChunk)) {
            $charset = 'UCS-4';
            $errorMsg = "Received content in unexpected character set: $charset (BOM found)";
        } elseif (preg_match('/^(?:\xFE\xFF|\xFF\xFE)/', $jsonChunk)) {
            $charset = 'UTF-16';
            $errorMsg = "Received content in unexpected character set: $charset (BOM found)";
        } elseif (preg_match('/^(?:\xEF\xBB\xBF)/', $jsonChunk)) {
            $charset = 'UTF-8';
            $errorMsg = "Received content with unexpected UTF-8 BOM";
        }

        // 3 - scan the first bytes of the data without a BOM
        /// @todo implement - see https://stackoverflow.com/questions/4990095/json-specification-and-usage-of-bom-charset-encoding

        if ($errorMsg !== '') {
            if (self::$logger === null) {
                self::$logger = Logger::instance();
            }

            self::$logger->error($errorMsg);
        }

        return $charset;
    }

    /**
     * Recursively convert charset encoding of data gotten from json decoding.
     *
     * @param mixed $data
     * @param false|string $targetCharset when false, use utf8_decode instead of mb_convert_encoding
     * @return void
     */
    protected function convertEncoding(&$data, $targetCharset)
    {
        $type = gettype($data);
        switch ($type) {
            case 'string':
                if ($targetCharset === false) {
                    $data = utf8_decode($data);
                } else {
                    $data = mb_convert_encoding($data, $targetCharset, 'UTF-8');
                }
                break;
            case 'array':
                foreach ($data as &$val) {
                    $this->convertEncoding($val, $targetCharset);
                }
                break;
        }
    }
}
