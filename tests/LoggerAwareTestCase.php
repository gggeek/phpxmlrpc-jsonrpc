<?php

include_once __DIR__ . '/parse_args.php';

include_once __DIR__ . '/../vendor/phpxmlrpc/phpxmlrpc/tests/PolyfillTestCase.php';

use PHPUnit\Runner\BaseTestRunner;

abstract class PhpJsonRpc_LoggerAwareTestCase extends PhpXmlRpc_PolyfillTestCase
{
    protected $args = array();

    protected $buffer = '';

    /**
     * Hide debug messages and errors unless we either are in debug mode or the test fails.
     * @return void
     */
    protected function set_up()
    {
        $this->args = JsonrpcArgParser::getArgs();

        if ($this->args['DEBUG'] == 0) {
            $this->debugBuffer = '';
            $this->errorBuffer = '';
            \PhpXmlRpc\PhpXmlRpc::setLogger($this);
        }
    }

    protected function tear_down()
    {
        if ($this->args['DEBUG'] > 0) {
            return;
        }

        // reset the logger to the default
        \PhpXmlRpc\PhpXmlRpc::setLogger(\PhpXmlRpc\Helper\Logger::instance());

        $status = $this->getStatus();
        if ($status == BaseTestRunner::STATUS_ERROR
            || $status == BaseTestRunner::STATUS_FAILURE) {
            echo $this->buffer;
        }
    }

    // logger API implementation

    public function debug($message, $context = array())
    {
        $this->buffer .= $message . "\n";
    }

    public function error($message, $context = array())
    {
        $this->buffer .= $message . "\n";
    }

    public function warning($message, $context = array())
    {
        $this->buffer .= $message . "\n";
    }
}
