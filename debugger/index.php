<?php

$vendorDir = __DIR__.'/../vendor';
if (!is_dir($vendorDir)) {
    $vendorDir = __DIR__.'/../../../../vendor';
}

if (!file_exists($vendorDir.'/autoload.php') || !file_exists($vendorDir.'/phpxmlrpc/phpxmlrpc/debugger/index.php'))
    die('Please install the dependencies using composer');

include_once($vendorDir.'/autoload.php');

define('DEFAULT_WSTYPE', '2');

include($vendorDir.'/phpxmlrpc/phpxmlrpc/debugger/index.php');
