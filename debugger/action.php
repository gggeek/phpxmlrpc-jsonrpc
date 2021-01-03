<?php

/// @todo support the case of this lib having been installed as dependency, too
if (!file_exists(__DIR__.'/../vendor/autoload.php') || !file_exists(__DIR__.'/../vendor/phpxmlrpc/phpxmlrpc/debugger/action.php'))
    die('Please install the dependencies using composer');

include_once(__DIR__.'/../vendor/autoload.php');

define('DEFAULT_WSTYPE', '1');

include(__DIR__.'/../vendor/phpxmlrpc/phpxmlrpc/debugger/action.php');
