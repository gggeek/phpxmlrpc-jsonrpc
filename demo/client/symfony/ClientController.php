<?php

namespace App\Controller;

use PhpXmlRpc\JsonRpc\Client;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Value;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * An example usage of the json-rpc client, configured as a service, from within a Symfony controller
 */
class ClientController extends AbstractController
{
    protected $client;

    public function __construct(Client $client, LoggerInterface $logger = null)
    {
        $this->client = $client;
        if ($logger) {
            PhpJsonRpc::setLogger($logger);
        }
    }

    #[Route('/getStateName/{stateNo}', name: 'getstatename', methods: ['GET'])]
    public function getStateName(int $stateNo): Response
    {
        $response = $this->client->send(new Request('examples.getStateName', [
            new Value($stateNo, Value::$xmlrpcInt)
        ]));
        if ($response->faultCode()) {
            throw new HttpException(502, $response->faultString());
        } else {
            return new Response("<html><body>State number $stateNo is: " . $response->value()->scalarVal() . '</body></html>');
        }
    }
}
