<?php

include_once __DIR__ . '/../vendor/phpxmlrpc/phpxmlrpc/tests/PolyfillTestCase.php';

use PhpXmlRpc\JsonRpc\Helper\Charset;
use PhpXmlRpc\JsonRpc\Helper\Parser;
use PhpXmlRpc\JsonRpc\Value;

class LoggerTest extends PhpXmlRpc_PolyfillTestCase
{
    protected $debugBuffer = '';
    protected $errorBuffer = '';
    protected $warningBuffer = '';

    protected function set_up()
    {
        $this->debugBuffer = '';
        $this->errorBuffer = '';
        $this->warningBuffer = '';
    }

    public function testCharsetAltLogger()
    {
        $ch = Charset::instance();
        $l = $ch->getLogger();
        Charset::setLogger($this);

        ob_start();
        $ch->encodeEntities('hello world', 'UTF-8', 'NOT-A-CHARSET');
        $o = ob_get_clean();
        $this->assertEquals('', $o);
        $this->assertStringContainsString("via mbstring: failed", $this->errorBuffer);

        Charset::setLogger($l);
    }

    public function testParserAltLogger()
    {
        $xp = new Parser();
        $l = $xp->getLogger();
        Parser::setLogger($this);

        ob_start();
        $ok = $xp->decodeJson('<?xml version="1.0" ?><methodResponse><params><param><value><boolean>x</boolean></value></param></params></methodResponse>');
        $o = ob_get_clean();
        $this->assertEquals(false, $ok);
        $this->assertEquals('', $o);
        $this->assertStringContainsString("JSON parsing failed", $this->errorBuffer);

        Parser::setLogger($l);
    }

    public function testDeprecations()
    {
        $v = new Value(array(), Value::$xmlrpcStruct);
        $l = $v->getLogger();
        Value::setLogger($this);
        \PhpXmlRpc\PhpXmlRpc::$xmlrpc_silence_deprecations = false;
        $c = $v->structSize();
        \PhpXmlRpc\PhpXmlRpc::$xmlrpc_silence_deprecations = true;
        Value::setLogger($l);
        $this->assertStringContainsString("Method PhpXmlRpc\Value::structSize is deprecated", $this->warningBuffer);
    }

    // logger API

    public function debug($message, $context = array())
    {
        $this->debugBuffer .= $message;
    }

    public function error($message, $context = array())
    {
        $this->errorBuffer .= $message;
    }

    public function warning($message, $context = array())
    {
        $this->warningBuffer .= $message;
    }
}
