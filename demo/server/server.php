<?php
/**
 * Demo server for jsonrpc library.
 *
 * Implements a lot of webservices, including a suite of services used for
 * interoperability testing (validator1 methods), and some whose only purpose
 * is to be used for unit-testing the library.
 *
 * Please _do not_ copy this file verbatim into your production server.
 **/

require_once __DIR__ . "/_prepend.php";

use PhpXmlRpc\JsonRpc\Response;
use PhpXmlRpc\JsonRpc\Server;
use PhpXmlRpc\JsonRpc\Value;

// Most of the code used to implement the webservices, and their signatures, are stowed away in neatly organized
// files, each demoing a different topic

// The simplest way of implementing webservices: as xmlrpc-aware global functions
$signatures1 = include(__DIR__.'/methodProviders/functions.php');

// Examples of exposing as webservices php functions and objects/methods which are not aware of xmlrpc classes
$signatures2 = include(__DIR__.'/methodProviders/wrapper.php');

// Definitions of webservices used for interoperability testing
$signatures3 = include(__DIR__.'/methodProviders/interop.php');
$signatures4 = include(__DIR__.'/methodProviders/validator1.php');

$signatures = array_merge(/*$signatures,*/ $signatures1, $signatures2, $signatures3, $signatures4);

$s = new Server($signatures, false);
$s->setdebug(0);
$s->compress_response = true;

// out-of-band information: let the client manipulate the server operations.
// we do this to help the testsuite script: do not reproduce in production!
if (isset($_GET['RESPONSE_ENCODING'])) {
    $s->response_charset_encoding = $_GET['RESPONSE_ENCODING'];
}

if (isset($_GET['EXCEPTION_HANDLING'])) {
    $s->exception_handling = $_GET['EXCEPTION_HANDLING'];
}
if (isset($_GET['FORCE_AUTH'])) {
    // We implement both  Basic and Digest auth in php to avoid having to set it up in a vhost.
    // Code taken from php.net
    // NB: we do NOT check for valid credentials!
    if ($_GET['FORCE_AUTH'] == 'Basic') {
        if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['REMOTE_USER']) && !isset($_SERVER['REDIRECT_REMOTE_USER'])) {
            header('HTTP/1.0 401 Unauthorized');
            header('WWW-Authenticate: Basic realm="Phpjsonrpc Basic Realm"');
            die('Text visible if user hits Cancel button');
        }
    } elseif ($_GET['FORCE_AUTH'] == 'Digest') {
        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="Phpxmlrpc Digest Realm",qop="auth",nonce="'.uniqid().'",opaque="'.md5('Phpxmlrpc Digest Realm').'"');
            die('Text visible if user hits Cancel button');
        }
    }
}

$s->service();
// that should do all we need!

require_once __DIR__ . "/_append.php";
