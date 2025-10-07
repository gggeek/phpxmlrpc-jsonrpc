<?php
/**
 * JSON extension to the PHP-XMLRPC lib
 *
 * For more info see:
 * http://www.json.org/
 * http://json-rpc.org/
 *
 * @author Gaetano Giunta
 * @copyright (c) 2005-2025 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 *
 **/

/******************************************************************************
 *
 * *** DEPRECATED ***
 *
 * This file is only used to insure backwards compatibility
 * with the previous API of the library
 *****************************************************************************/

include_once(__DIR__.'/../src/PhpJsonRpc.php');
include_once(__DIR__.'/../src/Traits/EncoderAware.php');
include_once(__DIR__.'/../src/Traits/JsonRpcVersionAware.php');
include_once(__DIR__.'/../src/Traits/SerializerAware.php');
include_once(__DIR__.'/../src/Helper/Charset.php');
include_once(__DIR__.'/../src/Helper/Parser.php');
include_once(__DIR__.'/../src/Helper/Serializer.php');
include_once(__DIR__.'/../src/Value.php');
include_once(__DIR__.'/../src/Request.php');
include_once(__DIR__.'/../src/Response.php');
include_once(__DIR__.'/../src/Client.php');
include_once(__DIR__.'/../src/Encoder.php');

use PhpXmlRpc\JsonRpc\Encoder;
use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;

// Note: the json spec omits \v, but it is present in ECMA-262, so we allow it
/// @deprecated
$GLOBALS['ecma262_entities'] = array(
    'b' => chr(8),
    'f' => chr(12),
    'n' => chr(10),
    'r' => chr(13),
    't' => chr(9),
    'v' => chr(11)
);

/// @deprecated
$GLOBALS['ecma262_iso88591_Entities'] = Charset::instance()->buildConversionTable();

/* Expose with the old names the classes which have been namespaced */

class jsonrpc_client extends \PhpXmlRpc\JsonRpc\Client
{
    // BC: default to full ASCII escaping of generated requests
    public $request_charset_encoding = '';

    // default to jsonrpc 1.0
    protected $jsonrpc_version = \PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_1_0;

    /// @todo bring back methods from xmlrpc_client which are missing in \PhpXmlRpc\JsonRpc\Client, if any
}

class jsonrpcmsg extends \PhpXmlRpc\JsonRpc\Request
{
    // default to jsonrpc 1.0
    protected $jsonrpc_version = PhpJsonRpc::VERSION_1_0;

    // undo the change in behaviour of the constructor
    public function __construct($methodName, $params = array(), $id = null, $jsonrpcVersion = null)
    {
        $originalId = $id;
        parent::__construct($methodName, $params = array(), $id = null, $jsonrpcVersion);
        if ($originalId === null) {
            $this->id = null;
        }
    }

}

class jsonrpcresp extends \PhpXmlRpc\JsonRpc\Response
{
    // default to jsonrpc 1.0
    protected $jsonrpc_version = PhpJsonRpc::VERSION_1_0;
}

class jsonrpcval extends \PhpXmlRpc\JsonRpc\Value
{
    /**
     * @deprecated
     * @param xmlrpcval $o
     * @return string
     */
    public function serializeval($o)
    {
        // add check? slower, but helps to avoid recursion in serializing broken xmlrpcvals...
        //if (is_object($o) && (get_class($o) == 'xmlrpcval' || is_subclass_of($o, 'xmlrpcval')))
        //{
        $ar = $o->me;
        $val = reset($ar);
        $typ = key($ar);

        return '<value>' . $this->serializedata($typ, $val) . "</value>\n";
        //}
    }

    /**
     * @deprecated this code looks like it is very fragile and has not been fixed for a long long time.
     * Shall we remove it for 2.0?
     */
    public function getval()
    {
        // UNSTABLE
        $b = reset($this->me);
        $a = key($this->me);
        // contributed by I Sofer, 2001-03-24
        // add support for nested arrays to scalarval
        // i've created a new method here, so as to
        // preserve back compatibility

        if (is_array($b)) {
            foreach($b as $id => $cont) {
                $b[$id] = $cont->scalarval();
            }
        }

        // add support for structures directly encoding php objects
        if (is_object($b)) {
            $t = get_object_vars($b);
            foreach($t as $id => $cont) {
                $t[$id] = $cont->scalarval();
            }
            foreach($t as $id => $cont) {
                @$b->$id = $cont;
            }
        }
        // end contrib
        return $b;
    }

    /// reset functionality added by parent class: same as it would happen if no interface was declared
    public function count()
    {
        return 1;
    }

    /// reset functionality added by parent class: same as it would happen if no interface was declared
    public function getIterator()
    {
        return new ArrayIterator($this);
    }
}

/* Expose as global functions the ones which are now class methods */

function php_jsonrpc_decode($jsonrpcVal, $options = array())
{
    $encoder = new Encoder();
    return $encoder->decode($jsonrpcVal, $options);
}

function php_jsonrpc_encode($phpVal, $options = array())
{
    $encoder = new Encoder();
    return $encoder->encode($phpVal, $options);
}

/*
 * BC: Request and Responses are now returned instead of Values even when there are more than 3 members in the parsed json hash
 */
function php_jsonrpc_decode_json($jsonVal, $options = array())
{
    $parser = new Parser();
    return $parser->decodeJson($jsonVal, $options);
}

/*
 * BC: $use_extension is now true
 * @todo throw if it is set to false?
 */
function jsonrpc_parse_req($data, $return_phpvals = false, $use_extension = true, $src_encoding = '')
{
    $parser = new Parser();
    return $parser->parseRequest($data, $return_phpvals, $src_encoding);
}

/*
 * BC: $use_extension is now true
 * @todo throw if it is set to false?
 */
function jsonrpc_parse_resp($data, $return_phpvals = false, $use_extension = true, $src_encoding = '')
{
    $parser = new Parser();
    return $parser->parseResponse($data, $return_phpvals, $src_encoding);
}

function serialize_jsonrpcresp($resp, $id = null, $charset_encoding = '')
{
    $serializer = new Serializer();
    return $serializer->serializeResponse($resp, $id, $charset_encoding);
}

function serialize_jsonrpcval($value, $charset_encoding = '')
{
    $serializer = new Serializer();
    return $serializer->serializeValue($value, $charset_encoding);
}

function php_2_jsonrpc_type($phptype)
{
    $parser = new Parser();
    return $parser->php2JsonrpcType($phptype);
}

function json_parse_native($data)
{
    $out = json_decode($data, true);
    if (!is_array($out)) {
        $GLOBALS['_xh']['isf_reason'] = 'JSON parsing failed';
        return false;
    } else {
        $GLOBALS['_xh']['value'] = $out;
        return true;
    }
}
