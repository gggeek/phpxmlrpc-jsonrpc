<?php
/**
 * JSON extension to the PHP-XMLRPC lib: server components
 *
 * For more info see:
 * http://www.json.org/
 * http://json-rpc.org/
 *
 * @author Gaetano Giunta
 * @copyright (c) 2005-2020 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 **/

/******************************************************************************
 *
 * *** DEPRECATED ***
 *
 * This file is only used to insure backwards compatibility
 * with the previous API of the library
 *****************************************************************************/

include_once(__DIR__.'/../src/Server.php');

class jsonrpc_server extends \PhpXmlRpc\JsonRpc\Server
{
}
