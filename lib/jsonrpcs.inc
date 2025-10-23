<?php
/**
 * JSON-RPC extension to the PHP-XMLRPC lib: server components
 *
 * For more info see:
 * https://www.jsonrpc.org/
 *
 * @author Gaetano Giunta
 * @copyright (c) 2005-2025 G. Giunta
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
    public function &__get($name)
    {
        switch($name) {
            case 'dmap':
                return $this->dmap;
            default:
                return parent::__get($name);
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $name === 'dmap' ? true : parent::__isset($name);
    }
}
