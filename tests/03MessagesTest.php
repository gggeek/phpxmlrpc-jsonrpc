<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

/**
 * Tests involving Requests and Responses, except for the parsing part
 */
class MessagesTest extends PhpJsonRpc_LoggerAwareTestCase
{
    public function testSerializeRequestJsonRpc1()
    {
        $r = new \PhpXmlRpc\JsonRpc\Request('hello', array(), null, \PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_1_0);
        $v = $r->serialize();
        /// @todo use a regexp in case json formatting gets different
        $this->assertStringContainsString('"method": "hello"', $v);
        $this->assertStringNotContainsString('"jsonrpc"', $v);
    }

    public function testSerializeRequestJsonRpc2()
    {
        $r = new \PhpXmlRpc\JsonRpc\Request('hello', array(), null, \PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_2_0);
        $r->setJsonRpcVersion(\PhpXmlRpc\JsonRpc\PhpJsonRpc::VERSION_2_0);
        $v = $r->serialize();
        /// @todo use a regexp in case json formatting gets different
        $this->assertStringContainsString('"method": "hello"', $v);
        $this->assertStringContainsString('"jsonrpc": "2.0"', $v);
    }


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

    public function testNotificationId()
    {
        $r = new \PhpXmlRpc\JsonRpc\Notification('hello', array());
        $this->assertSame(null, $r->id());
    }

    /**
     * Checks that the JsonRpc and XmlRpc parsers do not step on each other
     */
    public function testParserClassOverride()
    {
        \PhpXmlRpc\JsonRpc\Request::setParser(new stdClass());
        $r = new \PhpXmlRpc\JsonRpc\Request('test', array());
        $this->assertInstanceOf('stdClass', $r->getParser());

        $xr = new \PhpXmlRpc\Request('test', array());
        $this->assertNotInstanceOf('stdClass', $xr->getParser());

        \PhpXmlRpc\Request::setParser($r);
        $this->assertInstanceOf('stdClass', $r->getParser());

        /// @todo also reinstate these in case of failure
        \PhpXmlRpc\JsonRpc\Request::setParser(new \PhpXmlRpc\JsonRpc\Helper\Parser());
        \PhpXmlRpc\Request::setParser(new \PhpXmlRpc\Helper\XMLParser());
    }
}
