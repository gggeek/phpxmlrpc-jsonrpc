<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

/**
 * Tests involving Requests and Responses, except for the parsing part
 */
class MessagesTest extends PhpJsonRpc_LoggerAwareTestCase
{
    public function testSerializePHPValResponseJsonRpc1()
    {
        $r = new \PhpXmlRpc\JsonRpc\Response(array('hello' => 'world'), 0, '', 'phpvals');
        $r->setJsonRpcVersion(\PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_1_0);
        $v = $r->serialize();
        /// @todo use a regexp in case json formatting gets different
        $this->assertStringContainsString('"result": {"hello":"world"}', $v);
        $this->assertStringNotContainsString('"jsonrpc"', $v);
    }

    public function testSerializePHPValResponseJsonRpc2()
    {
        $r = new \PhpXmlRpc\JsonRpc\Response(array('hello' => 'world'), 0, '', 'phpvals');
        $r->setJsonRpcVersion(\PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_2_0);
        $v = $r->serialize();
        /// @todo use a regexp in case json formatting gets different
        $this->assertStringContainsString('"result": {"hello":"world"}', $v);
        $this->assertStringContainsString('"jsonrpc": "2.0"', $v);
    }

    public function testRequestIds()
    {
        $r = new \PhpXmlRpc\JsonRpc\Request('hello');
        $this->assertNotNull($r->id());

        $r = new \PhpXmlRpc\JsonRpc\Request('hello', array(), -1);
        $this->assertSame(-1, $r->id());

        $r = new \PhpXmlRpc\JsonRpc\Request('hello', array(), '1');
        $this->assertSame('1', $r->id());
    }
}
