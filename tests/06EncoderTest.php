<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

use PhpXmlRpc\JsonRpc\Encoder;

/**
 * Tests involving automatic encoding/decoding of php values into xmlrpc values (the Encoder class).
 *
 * @todo add tests for encoding options: 'encode_php_objs', 'auto_dates', 'null_extension'
 * @todo add tests for decoding
 */
class EncoderTest extends PhpJsonRpc_LoggerAwareTestCase
{
    public function testEncodeArray()
    {
        $e = new Encoder();
        $v = $e->encode(array());
        $this->assertEquals('array', $v->kindOf());

        $r = range(1, 10);
        $v = $e->encode($r);
        $this->assertEquals('array', $v->kindOf());

        $r['.'] = '...';
        $v = $e->encode($r);
        $this->assertEquals('struct', $v->kindOf());
    }

    public function testEncodeDate()
    {
        $e = new Encoder();
        $r = new DateTime();
        $v = $e->encode($r, array('auto_dates'));
        $this->assertEquals('dateTime.iso8601', $v->scalarTyp());
    }

    public function testEncodeRecursive()
    {
        $e = new Encoder();
        $v = $e->encode($e->encode('a simple string'));
        $this->assertEquals('scalar', $v->kindOf());
    }
}
