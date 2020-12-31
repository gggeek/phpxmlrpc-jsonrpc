<?php

if (!file_exists(__DIR__.'/../vendor/autoload.php') || !file_exists(__DIR__.'/../vendor/phpxmlrpc/phpxmlrpc/debugger/index.php'))
    die('Please install the dependencies using composer');

include_once(__DIR__.'/../vendor/autoload.php');

define('DEFAULT_WSTYPE', '1');

include(__DIR__.'/../vendor/phpxmlrpc/phpxmlrpc/debugger/action.php');
