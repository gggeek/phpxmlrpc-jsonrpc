<?php

namespace App\Controller;

use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Server;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ServerController extends AbstractController
{
    protected $server;

    public function __construct(Server $server, LoggerInterface $logger = null)
    {
        $this->server = $server;
        if ($logger) {
            PhpJsonRpc::setLogger($logger);
        }
    }

    # This single method serves ALL the json-rpc requests.
    # The configuration for which json-rpc methods exist and how they are handled is carried out in the service definition
    # of the Server in the constructor
    #[Route('/jsonrpc', name: 'json_rpc', methods: ['POST'])]
    public function serve(): Response
    {
        $jsonrpcResponse = $this->server->service(null, true);
        $response = new Response($jsonrpcResponse, 200, ['Content-Type' => 'application/json']);
        // there should be no need to disable response caching since this is only accessed via POST
        return $response;
    }
}
