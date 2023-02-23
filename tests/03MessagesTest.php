<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

/**
 * Tests involving Requests and Responses, except for the parsing part
 */
class MessagesTest extends PhpJsonRpc_LoggerAwareTestCase
{
    public function testSerializePHPValResponse()
    {
        $r = new \PhpXmlRpc\JsonRpc\Response(array('hello' => 'world'), 0, '', 'phpvals');
        $v = $r->serialize();
        /// @todo use a regexp in case json formatting gets different
        $this->assertStringContainsString('"result": {"hello":"world"}', $v);
    }
}
