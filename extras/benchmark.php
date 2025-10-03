<?php
/**
 * Benchmarking suite for the PHPJSONRPC lib
 *
 * @author Gaetano Giunta
 * @copyright (C) 2006-2025 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 *
 * @todo add a check for response ok in call testing
 * @todo add support for --help option to give users the list of supported parameters
 * @todo make number of test iterations flexible
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
    if (!isset($test_results[$test_name])) {
        $test_results[$test_name] = array();
    }
    $test_results[$test_name][$test_case] = array();
    $test_results[$test_name][$test_case]['time'] = microtime(true);
}

function end_test($test_name, $test_case, $test_result)
{
    global $test_results;
    $end = microtime(true);
    if (!isset($test_results[$test_name][$test_case])) {
        trigger_error('ending test that was not started');
    }
    $test_results[$test_name][$test_case]['time'] = $end - $test_results[$test_name][$test_case]['time'];
    $test_results[$test_name][$test_case]['result'] = $test_result;
    echo '.';
    flush();
    @ob_flush();
}

// Set up PHP structures to be used in many tests

$data1 = array(1, 1.0, 'hello world', true, null, -1, 11.0, '~!@#$%^&*()_+|', false, null);
$data2 = array('zero' => $data1, 'one' => $data1, 'two' => $data1, 'three' => $data1, 'four' => $data1, 'five' => $data1, 'six' => $data1, 'seven' => $data1, 'eight' => $data1, 'nine' => $data1);
$data = array($data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2, $data2);
$keys = array('zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine');

// Begin execution

$test_results = array();
$is_web = isset($_SERVER['REQUEST_METHOD']);
$xd = extension_loaded('xdebug') && ini_get('xdebug.profiler_enable');
if ($xd) {
    $num_tests = 1;
} else {
    $num_tests = 10;
}

$test_http = false; // enable/disable tests made over http

$title = 'JSON-RPC Benchmark Tests';

if ($is_web) {
    echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" lang=\"en\" xml:lang=\"en\">\n<head>\n<title>$title</title>\n</head>\n<body>\n<h1>$title</h1>\n<pre>\n";
} else {
    echo "$title\n\n";
}

if ($is_web) {
    echo "<h3>Using lib version: " . PhpJsonRpc::$jsonrpcVersion . " on PHP version: " . phpversion() . "</h3>\n";
    if ($xd) {
        echo "<h4>XDEBUG profiling enabled: skipping remote tests. Trace file is: " . htmlspecialchars(xdebug_get_profiler_filename()) . "</h4>\n";
    }
    flush();
    ob_flush();
} else {
    echo "Using lib version: " . PhpJsonRpc::$jsonrpcVersion . " on PHP version: " . phpversion() . "\n";
    if ($xd)  {
        echo "XDEBUG profiling enabled: skipping remote tests\nTrace file is: " . xdebug_get_profiler_filename() . "\n";
    }
}

// \PhpXmlRpc\JsonRpc\PhpJsonRpc::$json_decode_depth = 10240;

// test 'old style' data encoding vs. 'automatic style' encoding
begin_test('Data encoding (large array)', 'manual encoding');
for ($i = 0; $i < $num_tests; $i++) {
    $vals = array();
    for ($j = 0; $j < 10; $j++) {
        $valArray = array();
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
            $valArray[$key] = new Value($values, 'array');
        }
        $vals[] = new Value($valArray, 'struct');
    }
    $value = new Value($vals, 'array');
    $out = $value->serialize();
}
end_test('Data encoding (large array)', 'manual encoding', $out);

begin_test('Data encoding (large array)', 'automatic encoding');
$encoder = new Encoder();
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
$out = new Response($data);
$in = $out->serialize();

begin_test('Data decoding (large array)', 'manual decoding');
for ($i = 0; $i < $num_tests; $i++) {
    $response = $dummy->parseResponse($in, true);
    $value = $response->value();
    $result = array();
    foreach($value as $val1) {
        $out = array();
        foreach ($val1 as $name => $val) {
            $out[$name] = array();
            foreach($val as $data) {
                $out[$name][] = $data->scalarVal();
            }
        }
        $result[] = $out;
    }
}
end_test('Data decoding (large array)', 'manual decoding', $result);

begin_test('Data decoding (large array)', 'automatic decoding');
for ($i = 0; $i < $num_tests; $i++) {
    $response = $dummy->parseResponse($in, true, 'phpvals');
    $value = $response->value();
}
end_test('Data decoding (large array)', 'automatic decoding', $value);

begin_test('Data decoding (large array)', 'native decoding');
for ($i = 0; $i < $num_tests; $i++) {
    // to be fair to phpjsonrpc, we add http checks to every lib
    $response = $dummy->parseResponse($in, true, 'json');
    $value = json_decode($response->value(), true);
    // we do NO error checking here: bound to be faster...
    $value = $value['result'];
}
end_test('Data decoding (large array)', 'native decoding', $value);

/// @todo add all test permutations found in the phpxmlrpc benchmark

if ($test_http && !$xd) {

    $num_tests = 25;

    /// test many calls vs. keep-alives

    $value = $encoder->encode($data1);
    $req = new Request('interopEchoTests.echoValue', array($value));
    $reqs = array();
    for ($i = 0; $i < $num_tests; $i++)
        $reqs[] = $req;

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
        $resp = $c->send($req);
        $response[] = $resp->value();
    }
    end_test('Repeated send (small array)', 'http 10', $response);

    if (function_exists('curl_init')) {
        begin_test('Repeated send (small array)', 'http 11 w. keep-alive');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($req, 10, 'http11');
            $response[] = $resp->value();
        }
        end_test('Repeated send (small array)', 'http 11 w. keep-alive', $response);

        $c->setOption(Client::OPT_KEEPALIVE, false);
        begin_test('Repeated send (small array)', 'http 11');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($req, 10, 'http11');
            $response[] = $resp->value();
        }
        end_test('Repeated send (small array)', 'http 11', $response);
    }

    /*begin_test('Repeated send (small array)', 'multicall');
    $response = $c->send($reqs);
    end_test('Repeated send (small array)', 'multicall', $response);*/

    if (function_exists('gzinflate')) {
        $c->setOption(Client::OPT_ACCEPTED_COMPRESSION, array('gzip'));
        $c->setOption(Client::OPT_REQUEST_COMPRESSION, 'gzip');

        begin_test('Repeated send (small array)', 'http 10 w. compression');
        $response = array();
        for ($i = 0; $i < 25; $i++) {
            $resp = $c->send($req);
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


if ($is_web) {
    echo "\n</pre>\n</body>\n</html>\n";
}
