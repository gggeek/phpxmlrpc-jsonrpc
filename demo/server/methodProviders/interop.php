<?php
/**
 * Defines functions and signatures which can be registered as methods exposed by an XMLRPC Server.
 *
 * To use this, use something akin to:
 * $signatures = include('interop.php');
 *
 * Trivial interop tests
 * http://www.xmlrpc.com/stories/storyReader$1636
 */

use PhpXmlRpc\JsonRpc\Response;
use PhpXmlRpc\JsonRpc\Value;

$i_echoString_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcString));
$i_echoString_doc = "Echoes string.";

$i_echoStringArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoStringArray_doc = "Echoes string array.";

$i_echoInteger_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcInt));
$i_echoInteger_doc = "Echoes integer.";

$i_echoIntegerArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoIntegerArray_doc = "Echoes integer array.";

$i_echoFloat_sig = array(array(Value::$xmlrpcDouble, Value::$xmlrpcDouble));
$i_echoFloat_doc = "Echoes float.";

$i_echoFloatArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoFloatArray_doc = "Echoes float array.";

$i_echoStruct_sig = array(array(Value::$xmlrpcStruct, Value::$xmlrpcStruct));
$i_echoStruct_doc = "Echoes struct.";

$i_echoStructArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoStructArray_doc = "Echoes struct array.";

$i_echoValue_doc = "Echoes any value back.";
$i_echoValue_sig = array(array(Value::$xmlrpcValue, Value::$xmlrpcValue));

$i_echoBase64_sig = array(array(Value::$xmlrpcBase64, Value::$xmlrpcBase64));
$i_echoBase64_doc = "Echoes base64.";

$i_echoDate_sig = array(array(Value::$xmlrpcDateTime, Value::$xmlrpcDateTime));
$i_echoDate_doc = "Echoes dateTime.";

function i_echoParam($req)
{
    $s = $req->getParam(0);

    return new Response($s);
}

function i_echoString($req)
{
    return i_echoParam($req);
}

function i_echoInteger($req)
{
    return i_echoParam($req);
}

function i_echoFloat($req)
{
    return i_echoParam($req);
}

function i_echoStruct($req)
{
    return i_echoParam($req);
}

function i_echoStringArray($req)
{
    return i_echoParam($req);
}

function i_echoIntegerArray($req)
{
    return i_echoParam($req);
}

function i_echoFloatArray($req)
{
    return i_echoParam($req);
}

function i_echoStructArray($req)
{
    return i_echoParam($req);
}

function i_echoValue($req)
{
    return i_echoParam($req);
}

function i_echoBase64($req)
{
    return i_echoParam($req);
}

function i_echoDate($req)
{
    return i_echoParam($req);
}

$i_whichToolkit_sig = array(array(Value::$xmlrpcStruct));
$i_whichToolkit_doc = "Returns a struct containing the following strings: toolkitDocsUrl, toolkitName, toolkitVersion, toolkitOperatingSystem.";

function i_whichToolkit($req)
{
    global $SERVER_SOFTWARE;
    $ret = array(
        "toolkitDocsUrl" => "http://phpxmlrpc.sourceforge.net/",
        "toolkitName" => PhpXmlRpc\PhpXmlRpc::$xmlrpcName,
        "toolkitVersion" => PhpXmlRpc\PhpXmlRpc::$xmlrpcVersion,
        "toolkitOperatingSystem" => isset($SERVER_SOFTWARE) ? $SERVER_SOFTWARE : $_SERVER['SERVER_SOFTWARE'],
    );

    $encoder = new PhpXmlRpc\Encoder();
    return new Response($encoder->encode($ret));
}

return array(
    "interopEchoTests.echoString" => array(
        "function" => "i_echoString",
        "signature" => $i_echoString_sig,
        "docstring" => $i_echoString_doc,
    ),
    "interopEchoTests.echoStringArray" => array(
        "function" => "i_echoStringArray",
        "signature" => $i_echoStringArray_sig,
        "docstring" => $i_echoStringArray_doc,
    ),
    "interopEchoTests.echoInteger" => array(
        "function" => "i_echoInteger",
        "signature" => $i_echoInteger_sig,
        "docstring" => $i_echoInteger_doc,
    ),
    "interopEchoTests.echoIntegerArray" => array(
        "function" => "i_echoIntegerArray",
        "signature" => $i_echoIntegerArray_sig,
        "docstring" => $i_echoIntegerArray_doc,
    ),
    "interopEchoTests.echoFloat" => array(
        "function" => "i_echoFloat",
        "signature" => $i_echoFloat_sig,
        "docstring" => $i_echoFloat_doc,
    ),
    "interopEchoTests.echoFloatArray" => array(
        "function" => "i_echoFloatArray",
        "signature" => $i_echoFloatArray_sig,
        "docstring" => $i_echoFloatArray_doc,
    ),
    "interopEchoTests.echoStruct" => array(
        "function" => "i_echoStruct",
        "signature" => $i_echoStruct_sig,
        "docstring" => $i_echoStruct_doc,
    ),
    "interopEchoTests.echoStructArray" => array(
        "function" => "i_echoStructArray",
        "signature" => $i_echoStructArray_sig,
        "docstring" => $i_echoStructArray_doc,
    ),
    "interopEchoTests.echoValue" => array(
        "function" => "i_echoValue",
        "signature" => $i_echoValue_sig,
        "docstring" => $i_echoValue_doc,
    ),
    "interopEchoTests.echoBase64" => array(
        "function" => "i_echoBase64",
        "signature" => $i_echoBase64_sig,
        "docstring" => $i_echoBase64_doc,
    ),
    "interopEchoTests.echoDate" => array(
        "function" => "i_echoDate",
        "signature" => $i_echoDate_sig,
        "docstring" => $i_echoDate_doc,
    ),
    "interopEchoTests.whichToolkit" => array(
        "function" => "i_whichToolkit",
        "signature" => $i_whichToolkit_sig,
        "docstring" => $i_whichToolkit_doc,
    ),
);
