<?php
/**
 * JSON extension to the PHP-XMLRPC lib
 *
 * For more info see:
 * http://www.json.org/
 * http://json-rpc.org/
 *
 * @author Gaetano Giunta
 * @copyright (c) 2005-2021 G. Giunta
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

include_once(__DIR__.'/../src/Client.php');
include_once(__DIR__.'/../src/Encoder.php');
include_once(__DIR__.'/../src/Helper/Charset.php');
include_once(__DIR__.'/../src/Helper/Parser.php');
include_once(__DIR__.'/../src/Helper/Serializer.php');
include_once(__DIR__.'/../src/Request.php');
include_once(__DIR__.'/../src/Response.php');
include_once(__DIR__.'/../src/Value.php');
include_once(__DIR__.'/../src/Wrapper.php');

use PhpXmlRpc\JsonRpc\Encoder;
use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Helper\Serializer;
use PhpXmlRpc\JsonRpc\Wrapper;

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
}

class jsonrpcmsg extends \PhpXmlRpc\JsonRpc\Request
{
}

class jsonrpcresp extends \PhpXmlRpc\JsonRpc\Response
{
}

class jsonrpcval extends \PhpXmlRpc\JsonRpc\Value
{
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
    $wrapper = new Parser();
    return $wrapper->php2JsonrpcType($phptype);
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
