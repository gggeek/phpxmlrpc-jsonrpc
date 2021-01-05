<?php
/**
 * JSON extension to the PHP-XMLRPC lib: server components
 *
 * For more info see:
 * http://www.json.org/
 * http://json-rpc.org/
 *
 * @author Gaetano Giunta
 * @copyright (c) 2005-2021 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 **/

/******************************************************************************
 *
 * *** DEPRECATED ***
 *
 * This file is only used to insure backwards compatibility
 * with the previous API of the library
 *****************************************************************************/

include_once(__DIR__.'/../src/Response.php');
include_once(__DIR__.'/../src/Server.php');
include_once(__DIR__.'/../src/Value.php');

class jsonrpc_server extends \PhpXmlRpc\JsonRpc\Server
{
    // bring back methods from xmlrpc_server which are missing in \PhpXmlRpc\JsonRpc\Server

    /**
     * A debugging routine: just echoes back the input packet as a string value
     * @deprecated
     */
    public function echoInput()
    {
        $r = new PhpXmlRpc\JsonRpc\Response(new PhpXmlRpc\JsonRpc\Value("'Aha said I: '" . file_get_contents('php://input'), 'string'));
        print $r->serialize();
    }

    /**
     * Reinstate access to class members which became protected/private
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        switch($name) {
            case 'dmap':
                return $this->dmap;
            default:
                trigger_error('Undefined property via __get(): ' . $name . ' in ' . $trace[0]['file'] . ' on line ' .
                    $trace[0]['line'], E_USER_NOTICE);
                return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $name === 'dmap';
    }
}
