<?php

include_once __DIR__ . '/LoggerAwareTestCase.php';

use PhpXmlRpc\JsonRpc\Value;

/**
 * Tests involving the Value class.
 * NB: these tests do not involve the parsing of xml into Value objects - look in 04ParsingTest for that
 */
class ValueTest extends PhpJsonRpc_LoggerAwareTestCase
{
    public function testMinusOneString()
    {
        $v = new Value('-1');
        $u = new Value('-1', 'string');
        $t = new Value(-1, 'string');
        $this->assertEquals($v->scalarVal(), $u->scalarVal());
        $this->assertEquals($v->scalarVal(), $t->scalarVal());
    }

    /**
     * This looks funny, and we might call it a bug. But we strive for 100 backwards compat...
     */
    public function testMinusOneInt()
    {
        $u = new Value();
        $v = new Value(-1);
        $this->assertEquals($u->scalarVal(), $v->scalarVal());
    }

    public function testAddScalarToStruct()
    {
        $v = new Value(array('a' => 'b'), 'struct');
        $r = $v->addScalar('c');
        $this->assertEquals(0, $r);
    }

    public function testAddStructToStruct()
    {
        $v = new Value(array('a' => new Value('b')), 'struct');
        $r = $v->addStruct(array('b' => new Value('c')));
        $this->assertEquals(2, $v->structsize());
        $this->assertEquals(1, $r);
        $r = $v->addStruct(array('b' => new Value('b')));
        $this->assertEquals(2, $v->structsize());
    }

    public function testAddArrayToArray()
    {
        $v = new Value(array(new Value('a'), new Value('b')), 'array');
        $r = $v->addArray(array(new Value('b'), new Value('c')));
        $this->assertEquals(4, $v->arraySize());
        $this->assertEquals(1, $r);
    }

    public function testUTF8String()
    {
        $sendstring = 'κόσμε'; // Greek word 'kosme'
        $f = new Value($sendstring, 'string');
        $v = $f->serialize();
        $this->assertEquals('"\u03ba\u1f79\u03c3\u03bc\u03b5"', $v);
        $v = $f->serialize('UTF-8');
        $this->assertEquals("\"$sendstring\"", $v);

    }

    public function testStringInt()
    {
        $v = new Value('hello world', 'int');
        $s = $v->serialize();
        $this->assertEquals("0", $s);
    }

    public function testStructMemExists()
    {
        $v = new Value(array('hello' => new Value('world')), 'struct');
        $b = isset($v['hello']);
        $this->assertEquals(true, $b);
        $b = isset($v['world']);
        $this->assertEquals(false, $b);
    }

    public function testLocale()
    {
        $locale = setlocale(LC_NUMERIC, 0);
        /// @todo on php 5.3/win, possibly later versions, setting locale to german does not seem to set decimal separator to comma...
        if (setlocale(LC_NUMERIC, 'deu', 'de_DE@euro', 'de_DE', 'de', 'ge') !== false) {
            $v = new Value(1.1, 'double');
            if (strpos($v->scalarVal(), ',') == 1) {
                $r = $v->serialize();
                $this->assertEquals(false, strpos($r, ','));
                setlocale(LC_NUMERIC, $locale);
            } else {
                setlocale(LC_NUMERIC, $locale);
                $this->markTestSkipped('did not find a locale which sets decimal separator to comma');
            }
        } else {
            $this->markTestSkipped('did not find a locale which sets decimal separator to comma');
        }
    }

    public function testArrayAccess()
    {
        $v2 = new Value(array(new Value('one'), new Value('two')), 'array');
        $this->assertEquals(2, count($v2));
        $out = array(array('key' => 0, 'value'  => 'object'), array('key' => 1, 'value'  => 'object'));
        $i = 0;
        foreach($v2 as $key => $val)
        {
            $expected = $out[$i];
            $this->assertEquals($expected['key'], $key);
            $this->assertEquals($expected['value'], gettype($val));
            $i++;
        }

        $v3 = new Value(10, 'i4');
        $this->assertEquals(1, count($v3));
        $this->assertEquals(true, isset($v3['int']));
        $this->assertEquals(true, isset($v3['i4']));
        $this->assertEquals(10, $v3['int']);
        $this->assertEquals(10, $v3['i4']);
        $v3['int'] = 100;
        $this->assertEquals(100, $v3['int']);
        $this->assertEquals(100, $v3['i4']);
        $v3['i4'] = 1000;
        $this->assertEquals(1000, $v3['int']);
        $this->assertEquals(1000, $v3['i4']);
    }

    public function testLatin15InternalEncoding()
    {
        if (!function_exists('mb_convert_encoding')) {
            $this->markTestSkipped('Miss mbstring extension to test exotic charsets');
            return;
        }

        $string = chr(164);
        $v = new Value($string);

        $originalEncoding = \PhpXmlRpc\PhpXmlRpc::$xmlrpc_internalencoding;
        \PhpXmlRpc\PhpXmlRpc::$xmlrpc_internalencoding = 'ISO-8859-15';

        $this->assertEquals('"\u20ac"', trim($v->serialize('US-ASCII')));
        $this->assertEquals("\"$string\"", trim($v->serialize('ISO-8859-15')));
        $this->assertEquals('"€"', trim($v->serialize('UTF-8')));

        \PhpXmlRpc\PhpXmlRpc::$xmlrpc_internalencoding = $originalEncoding;
    }
}
