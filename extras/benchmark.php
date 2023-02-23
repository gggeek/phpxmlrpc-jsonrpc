<?php
/**
 * Benchmarking suite
 *
 * @author Gaetano Giunta
 * @copyright (c) 2006-2023 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 **/

use PhpXmlRpc\JsonRpc\Client;
use PhpXmlRpc\JsonRpc\Encoder;
use PhpXmlRpc\JsonRpc\PhpJsonRpc;
use PhpXmlRpc\JsonRpc\Request;
use PhpXmlRpc\JsonRpc\Response;
use PhpXmlRpc\JsonRpc\Value;

// support being installed both as top-level project and as dependency
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    include_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    include_once __DIR__ . '/../vendor/autoload.php';
}

include __DIR__ . '/../tests/parse_args.php';
$args = JsonrpcArgParser::getArgs();

/// @todo fix: usage of undefined vars $LOCALSERVER, $URI

function begin_test($test_name, $test_case)
{
    global $test_results;
    if (!isset($test_results[$test_name]))
        $test_results[$test_name] = array();
    $test_results[$test_name][$test_case] = array();
    list($micro, $sec) = explode(' ', microtime());
    $test_results[$test_name][$test_case]['time'] = $sec + $micro;
}

function end_test($test_name, $test_case, $test_result)
{
    global $test_results;
    list($micro, $sec) = explode(' ', microtime());
    if (!isset($test_results[$test_name][$test_case]))
        trigger_error('ending test that was not started');
    $test_results[$test_name][$test_case]['time'] = $sec + $micro - $test_results[$test_name][$test_case]['time'];
    $test_results[$test_name][$test_case]['result'] = $test_result;

    echo '.';
    flush();
}

// Set up PHP structures to be used in many tests

$data1 = array(1, 1.0, 'hello world', true, null, -1, 11.0, '~!@#$%^&*()_+|', false, null);
$data2 = array('zero' => $data1, 'one' => $data1, 'two' => $data1, 'three' => $data1, 'four' => $data1, 'five' => $data1, 'six' => $data1, 'seven' => $data1, 'eight' => $data1, 'nine' => $data1);
$data = array($data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2);
$keys = array('zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine');

$test_results = array();
$xd = extension_loaded('xdebug') && ini_get('xdebug.profiler_enable');
if ($xd)
    $num_tests = 1;
else
    $num_tests = 10;

$test_http = false; // enable/disable tests made over http

$title = 'JSON-RPC Benchmark Tests';

if (isset($_SERVER['REQUEST_METHOD'])) {
    echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" lang=\"en\" xml:lang=\"en\">\n<head>\n<title>$title</title>\n</head>\n<body>\n<h1>$title</h1>\n<pre>\n";
    if ($xd) echo "<h4>XDEBUG profiling enabled: skipping remote tests. Trace file is: " . htmlspecialchars(xdebug_get_profiler_filename()) . "</h4>\n";
    flush();
    if ($xd) echo "XDEBUG profiling enabled: skipping remote tests\nTrace file is: " . xdebug_get_profiler_filename() . "\n";
} else {
    echo "$title\n\n";
}

if (isset($_SERVER['REQUEST_METHOD'])) {
    echo "<h3>Using lib version: " . PhpJsonRpc::$jsonrpcVersion . " on PHP version: " . phpversion() . "</h3>\n";
    flush();
} else {
    echo "Using lib version: " . PhpJsonRpc::$jsonrpcVersion . " on PHP version: " . phpversion() . "\n";
}

$encoder = new Encoder();

// test 'old style' data encoding vs. 'automatic style' encoding
begin_test('Data encoding (large array)', 'manual encoding');
for ($i = 0; $i < $num_tests; $i++) {
    $vals = array();
    for ($j = 0; $j < 10; $j++) {
        $valarray = array();
        foreach ($data[$j] as $key => $val) {
            $values = array();
            $values[] = new Value($val[0], 'int');
            $values[] = new Value($val[1], 'double');
            $values[] = new Value($val[2], 'string');
            $values[] = new Value($val[3], 'boolean');
            $values[] = new Value($val[4], 'null');
            $values[] = new Value($val[5], 'int');
            $values[] = new Value($val[6], 'double');
            $values[] = new Value($val[7], 'string');
            $values[] = new Value($val[8], 'boolean');
            $values[] = new Value($val[9], 'null');
            $valarray[$key] = new Value($values, 'array');
        }
        $vals[] = new Value($valarray, 'struct');
    }
    $value = new Value($vals, 'array');
    $out = $value->serialize();
}
end_test('Data encoding (large array)', 'manual encoding', $out);

begin_test('Data encoding (large array)', 'automatic encoding');
for ($i = 0; $i < $num_tests; $i++) {
    $value = $encoder->encode($data);
    $out = $value->serialize();
}
end_test('Data encoding (large array)', 'automatic encoding', $out);

begin_test('Data encoding (large array)', 'native encoding');
for ($i = 0; $i < $num_tests; $i++) {
    $out = json_encode($data);
}
end_test('Data encoding (large array)', 'native encoding', $out);


// test 'old style' data decoding vs. 'automatic style' decoding
$dummy = new Request('');
$out = new Response($value);
$in = $out->serialize();

begin_test('Data decoding (large array)', 'manual decoding');
for ($i = 0; $i < $num_tests; $i++) {
    $response = $dummy->ParseResponse($in, true);
    $value = $response->value();
    $result = array();
    for ($k = 0; $k < count($value); $k++) {
        $val1 = $value[$k];
        $out = array();
        foreach ($val1 as $name => $val) {
            $out[$name] = array();
            for ($j = 0; $j < count($val); $j++) {
                $data = $val[$j];
                $out[$name][] = $data->scalarVal();
            }
        } // while
        $result[] = $out;
    }
}
end_test('Data decoding (large array)', 'manual decoding', $result);

begin_test('Data decoding (large array)', 'automatic decoding');
for ($i = 0; $i < $num_tests; $i++) {
    $response = $dummy->ParseResponse($in, true, 'phpvals');
    $value = $response->value();
}
end_test('Data decoding (large array)', 'automatic decoding', $value);

begin_test('Data decoding (large array)', 'native decoding');
for ($i = 0; $i < $num_tests; $i++) {
    // to be fair to phpjsonrpc, we add http checks to every lib
    $response = $dummy->ParseResponse($in, true, 'json');
    $value = json_decode($response->value(), true);
    // we do NO error checking here: bound to be faster...
    $value = $value['result'];
}
end_test('Data decoding (large array)', 'native decoding', $value);



if ($test_http && !$xd) {

    /// test many calls vs. keep-alives
    $value = $encoder->encode($data1);
    $msg = new Request('interopEchoTests.echoValue', array($value));
    $msgs = array();
    for ($i = 0; $i < 25; $i++)
        $msgs[] = $msg;
    $server = explode(':', $args['HTTPSERVER']);
    if (count($server) > 1) {
        $c = new Client($args['HTTPURI'], $server[0], $server[1]);
    } else {
        $c = new Client($args['HTTPURI'], $args['HTTPSERVER']);
    }
    // do not interfere with http compression
    $c->setOption(Client::OPT_ACCEPTED_COMPRESSION, array());
    //$c->debug=true;

    begin_test('Repeated send (small array)', 'http 10');
    $response = array();
    for ($i = 0; $i < 25; $i++) {
        $resp = $c->send($msg);
        $response[] = $resp->value();
    }
    end_test('Repeated send (small array)', 'http 10', $response);

    if (function_exists('curl_init')) {
        begin_test('Repeated send (small array)', 'http 11 w. keep-alive');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($msg, 10, 'http11');
            $response[] = $resp->value();
        }
        end_test('Repeated send (small array)', 'http 11 w. keep-alive', $response);

        $c->setOption(Client::OPT_KEEPALIVE, false);
        begin_test('Repeated send (small array)', 'http 11');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($msg, 10, 'http11');
            $response[] = $resp->value();
        }
        end_test('Repeated send (small array)', 'http 11', $response);
    }

    /*begin_test('Repeated send (small array)', 'multicall');
    $response = $c->send($msgs);
    end_test('Repeated send (small array)', 'multicall', $response);*/

    if (function_exists('gzinflate')) {
        $c->setOption(Client::OPT_ACCEPTED_COMPRESSION, array('gzip'));
        $c->setOption(Client::OPT_REQUEST_COMPRESSION, 'gzip');

        begin_test('Repeated send (small array)', 'http 10 w. compression');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($msg);
            $response[] = $resp->value();
        }
        end_test('Repeated send (small array)', 'http 10 w. compression', $response);
    }

}

echo "\n";
foreach ($test_results as $test => $results) {
    echo "\nTEST: $test\n";
    foreach ($results as $case => $data) {
        echo "  $case: {$data['time']} secs - Output data CRC: " . crc32(serialize($data['result'])) . "\n";
    }
}


if (isset($_SERVER['REQUEST_METHOD'])) {
    echo "\n</pre>\n</body>\n</html>\n";
}
