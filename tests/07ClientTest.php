<?php

include_once __DIR__ . '/ServerAwareTestCase.php';

use PhpXmlRpc\JsonRpc\Client;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Value;
use PhpXmlRpc\Helper\Interop;

/**
 * Tests involving the Client class (and mostly no server).
 */
class ClientTest extends PhpJsonRpc_ServerAwareTestCase
{
    /** @var Client */
    public $client = null;
    protected $timeout = 10;

    public function set_up()
    {
        parent::set_up();

        $this->client = $this->getClient();
    }

    /**
     * @dataProvider getAvailableJsonRpcVersions
     */
    public function test404($jsonRpcVersion)
    {
        $this->client->setJsonRpcVersion($jsonRpcVersion);

        $this->client->path = '/NOTEXIST.php';

        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $r = $this->client->send($m, $this->timeout);
        if ($jsonRpcVersion == PhpJsonRpc::VERSION_2_0) {
            $this->assertEquals(Interop::$xmlrpcerr['http_error'], $r->faultCode());
        } else {
            $this->assertEquals(\PhpXmlRpc\PhpXmlRpc::$xmlrpcerr['http_error'], $r->faultCode());
        }
        $this->assertEquals($m->id(), $r->id(), 'Response Id is different from request Id');
        $this->assertEquals($m->getJsonRpcVersion(), $r->getJsonRpcVersion(), 'Response version is different from request version');
    }

    /**
     * @dataProvider getAvailableJsonRpcVersions
     */
    public function test404Interop($jsonRpcVersion)
    {
        $this->client->setJsonRpcVersion($jsonRpcVersion);

        $this->client->path = '/NOTEXIST.php';

        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $orig = \PhpXmlRpc\PhpXmlRpc::$xmlrpcerr;
        \PhpXmlRpc\PhpXmlRpc::useInteropFaults();
        $r = $this->client->send($m, $this->timeout);
        $this->assertEquals(Interop::$xmlrpcerr['http_error'], $r->faultCode());
         /// @todo reset this via tear_down
        \PhpXmlRpc\PhpXmlRpc::$xmlrpcerr = $orig;
    }

    /**
     * @dataProvider getAvailableJsonRpcVersions
     */
    public function testUnsupportedAuth($jsonRpcVersion)
    {
        $this->client->setJsonRpcVersion($jsonRpcVersion);

        $m = new Request('examples.echo', array(
            new Value('hello', 'string'),
        ));
        $this->client->setOption(Client::OPT_USERNAME, 'user');
        $this->client->setOption(Client::OPT_AUTH_TYPE, 2);
        $this->client->setOption(Client::OPT_USE_CURL, Client::USE_CURL_NEVER);
        $r = $this->client->send($m);
        $this->assertEquals(\PhpXmlRpc\PhpXmlRpc::$xmlrpcerr['unsupported_option'], $r->faultCode());
        $this->assertEquals($m->id(), $r->id(), 'Response Id is different from request Id');
        $this->assertEquals($m->getJsonRpcVersion(), $r->getJsonRpcVersion(), 'Response version is different from request version');
    }

    /**
     * @dataProvider getAvailableJsonRpcVersions
     */
    public function testSrvNotFound($jsonRpcVersion)
    {
        $this->client->setJsonRpcVersion($jsonRpcVersion);

        $this->client->server .= 'XXX';
        // make sure there's no freaking catchall DNS in effect
        $dnsinfo = @dns_get_record($this->client->server);
        if ($dnsinfo) {
            $this->markTestSkipped('Seems like there is a catchall DNS in effect: host ' . $this->client->server . ' found');
        } else {
            $m = new Request('examples.echo', array(
                new Value('hello', 'string'),
            ));
            $r = $this->client->send($m, 5);
            if ($jsonRpcVersion == PhpJsonRpc::VERSION_2_0) {
                $this->assertEquals(Interop::$xmlrpcerr['http_error'], $r->faultCode());
            } else {
                $this->assertEquals(\PhpXmlRpc\PhpXmlRpc::$xmlrpcerr['http_error'], $r->faultCode());
            }

            $this->assertEquals($m->id(), $r->id(), 'Response Id is different from request Id');
            $this->assertEquals($m->getJsonRpcVersion(), $r->getJsonRpcVersion(), 'Response version is different from request version');
        }
    }

    public function testCurlKAErr()
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('CURL missing: cannot test curl keepalive errors');
        }

        $m = new Request('examples.stringecho', array(
            new Value('hello', 'string'),
        ));
        // test 2 calls w. keepalive: 1st time connection ko, second time ok
        $this->client->server .= 'XXX';
        $this->client->keepalive = true;
        $r = $this->client->send($m, $this->timeout, 'http11');
        // in case we have a "universal dns resolver" getting in the way, we might get a 302 instead of a 404
        $this->assertTrue($r->faultCode() === 8 || $r->faultCode() == 5);

        // now test a successful connection
        $server = explode(':', $this->args['HTTPSERVER']);
        if (count($server) > 1) {
            $this->client->port = $server[1];
        }
        $this->client->server = $server[0];
        //$this->client->path = $this->args['HTTPURI'];
        //$this->client->setCookie('PHPUNIT_RANDOM_TEST_ID', static::$randId);
        $r = $this->client->send($m, $this->timeout, 'http11');
        $this->assertEquals(0, $r->faultCode());
        $ro = $r->value();
        is_object($ro) && $this->assertEquals('hello', $ro->scalarVal());
    }

    /**
     * @dataProvider getAvailableUseCurlOptions
     */
    public function testCustomHeaders($curlOpt)
    {
        $this->client->setOption(Client::OPT_USE_CURL, $curlOpt);
        $this->client->setOption(Client::OPT_EXTRA_HEADERS, array('X-PJR-Test: yes'));
        $r = new Request('tests.getallheaders');
        $r = $this->client->send($r);
        $this->assertEquals(0, $r->faultCode());
        $ro = $r->value();
        $this->assertArrayHasKey('X-Pjr-Test', $ro->scalarVal(), "Testing with curl mode: $curlOpt");
    }

    /// @todo add more permutations, eg. check that PHP_URL_SCHEME is ok with http10, http11, h2 etc...
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
