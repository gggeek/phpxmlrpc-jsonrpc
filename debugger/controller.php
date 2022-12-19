<?php

$vendorDir = __DIR__.'/../vendor';
if (!is_dir($vendorDir)) {
    $vendorDir = __DIR__.'/../../../../vendor';
}

if (!file_exists($vendorDir.'/autoload.php') || !file_exists($vendorDir.'/phpxmlrpc/phpxmlrpc/debugger/controller.php'))
    die('Please install the dependencies using composer');

include_once($vendorDir.'/autoload.php');

define('DEFAULT_WSTYPE', '1', false);

if (!defined('JSXMLRPC_PATH')) {
    // phpxmlrpc will be within vendors, whereas its default config does not expect that
    define('JSXMLRPC_PATH', '../../../../..', false);
    if (!defined('JSXMLRPC_BASEURL')) {
        define('JSXMLRPC_BASEURL', '../../jsxmlrpc/debugger/', false);
    }
}

include($vendorDir.'/phpxmlrpc/phpxmlrpc/debugger/controller.php');
