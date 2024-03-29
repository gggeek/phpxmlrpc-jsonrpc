<?php

include_once __DIR__ . '/ServerAwareTestCase.php';

use PhpXmlRpc\JsonRpc\Client;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Value;

/**
 * Tests involving the Client class (and mostly no server).
 */
class ClientTest extends PhpJsonRpc_ServerAwareTestCase
{
    /** @var xmlrpc_client $client */
    public $client = null;

    public function set_up()
    {
        parent::set_up();

        $this->client = new Client('/NOTEXIST.php', $this->args['HTTPSERVER'], 80);
        $this->client->setDebug($this->args['DEBUG']);
    }

    public function test404()
    {
        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $r = $this->client->send($m, 5);
        $this->assertEquals(5, $r->faultCode());
    }

    public function test404Interop()
    {
        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $orig = \PhpXmlRpc\PhpXmlRpc::$xmlrpcerr;
        \PhpXmlRpc\PhpXmlRpc::useInteropFaults();
        $r = $this->client->send($m, 5);
        $this->assertEquals(-32300, $r->faultCode());
        \PhpXmlRpc\PhpXmlRpc::$xmlrpcerr = $orig;
    }

    public function testUnsupportedAuth()
    {
        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $this->client->setOption(\PhpXmlRpc\Client::OPT_USERNAME, 'user');
        $this->client->setOption(\PhpXmlRpc\Client::OPT_AUTH_TYPE, 2);
        $this->client->setOption(\PhpXmlRpc\Client::OPT_USE_CURL, \PhpXmlRpc\Client::USE_CURL_NEVER);
        $r = $this->client->send($m);
        $this->assertEquals(\PhpXmlRpc\PhpXmlRpc::$xmlrpcerr['unsupported_option'], $r->faultCode());
    }

    public function testSrvNotFound()
    {
        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $this->client->server .= 'XXX';
        $dnsinfo = @dns_get_record($this->client->server);
        if ($dnsinfo) {
            $this->markTestSkipped('Seems like there is a catchall DNS in effect: host ' . $this->client->server . ' found');
        } else {
            $r = $this->client->send($m, 5);
            // make sure there's no freaking catchall DNS in effect
            $this->assertEquals(5, $r->faultCode());
        }
    }

    public function testCurlKAErr()
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('CURL missing: cannot test curl keepalive errors');

            return;
        }
        $m = new Request('examples.stringecho', array(
            new Value('hello', 'string'),
        ));
        // test 2 calls w. keepalive: 1st time connection ko, second time ok
        $this->client->server .= 'XXX';
        $this->client->keepalive = true;
        $r = $this->client->send($m, 5, 'http11');
        // in case we have a "universal dns resolver" getting in the way, we might get a 302 instead of a 404
        $this->assertTrue($r->faultCode() === 8 || $r->faultCode() == 5);

        // now test a successful connection
        $server = explode(':', $this->args['HTTPSERVER']);
        if (count($server) > 1) {
            $this->client->port = $server[1];
        }
        $this->client->server = $server[0];
        $this->client->path = $this->args['HTTPURI'];
        $this->client->setCookie('PHPUNIT_RANDOM_TEST_ID', static::$randId);
        $r = $this->client->send($m, 5, 'http11');
        $this->assertEquals(0, $r->faultCode());
        $ro = $r->value();
        is_object($ro) && $this->assertEquals('hello', $ro->scalarVal());
    }

    public function testgetUrl()
    {
        $m = $this->client->getUrl(PHP_URL_SCHEME);
        $this->assertEquals($m, $this->client->method);
        $h = $this->client->getUrl(PHP_URL_HOST);
        $this->assertEquals($h, $this->client->server);
        $p = $this->client->getUrl(PHP_URL_PORT);
        $this->assertEquals($p, $this->client->port);
        $p = $this->client->getUrl(PHP_URL_PATH);
        $this->assertEquals($p, $this->client->path);
    }
}
