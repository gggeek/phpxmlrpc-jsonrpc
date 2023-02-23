<?php
/**
 * Demo server for phpjsonrpc library - legacy API (v3).
 *
 * It mimics server.php, but does not rely on other autoload mechanisms than the loading of .inc files
 */

require_once __DIR__ . "/../../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpc.inc";
require_once __DIR__ . "/../../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpcs.inc";
require_once __DIR__ . "/../../lib/jsonrpc.inc";
require_once __DIR__ . "/../../lib/jsonrpcs.inc";

$signatures1 = include(__DIR__.'/methodProviders/functions.php');
$signatures2 = include(__DIR__.'/methodProviders/interop.php');
$signatures3 = include(__DIR__.'/methodProviders/validator1.php');
$signatures = array_merge($signatures1, $signatures2, $signatures3);

$s = new jsonrpc_server($signatures, false);
$s->setDebug(3);
$s->service();
