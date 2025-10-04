<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

/**
 * Tests involving value/request/response parsing.
 */
class ParsingTest extends PhpJsonRpc_LoggerAwareTestCase
{
    /**
     * The original equivalent test was used to stress the 10MB limit of the xml parser. Does the json parser have any
     * quirks relating to big messages?
     * @todo can we not use \PhpXmlRpc\JsonRpc\Encoder for this test?
     */
    public function testBigJson()
    {
        $data = array();
        for ($i = 0; $i < 500000; $i++ ) {
            $data[] = 'hello world';
        }

        $encoder = new \PhpXmlRpc\JsonRpc\Encoder();
        $val = $encoder->encode($data);
        $req = new \PhpXmlRpc\JsonRpc\Request('test', array($val));
        $json = $req->serialize();
        $parser = new \PhpXmlRpc\JsonRpc\Helper\Parser();
        $_xh = $parser->parseRequest($json);
        $this->assertNotEquals(false, $_xh);
        $this->assertEquals(0, $_xh['isf']);
    }
}
