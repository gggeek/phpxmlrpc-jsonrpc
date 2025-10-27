<?php
require_once __DIR__ . "/_prepend.php";

output('<html lang="en">
<head><title>phpjsonrpc - Which toolkit demo</title></head>
<body>
<h1>Which toolkit demo</h1>
<h2>Query server for toolkit information</h2>
<h3>The code demonstrates support for http redirects, the `interopEchoTests.whichToolkit` json-rpc method, request compression and use of pre-built json</h3>
<p>You can see the source to this page here: <a href="which.php?showSource=1">which.php</a></p>
');

use PhpXmlRpc\JsonRpc\Client;

// use a pre-built request payload
$payload = json_encode(array(
        'jsonrpc' => '2.0',
        'method' => 'interopEchoTests.whichToolkit',
        'id' => 1,
    ), JSON_PRETTY_PRINT
);
output("JSON custom request:<br/><pre>" . htmlspecialchars($payload) . "</pre>\n");

$client = new Client(JSONRPCSERVER);

// to support http redirects we have to force usage of cURL even for http 1.0 requests
$client->setOption(Client::OPT_USE_CURL, Client::USE_CURL_ALWAYS);
$client->setOption(Client::OPT_EXTRA_CURL_OPTS, array(CURLOPT_FOLLOWLOCATION => true, CURLOPT_POSTREDIR => 3));

// if we know that the server supports them, we can enable sending of compressed requests
$client->setOption(Client::OPT_REQUEST_COMPRESSION, 'gzip');

// ask the client to give us back json
$client->setOption(Client::OPT_RETURN_TYPE, 'json');

$client->setDebug(1);

$resp = $client->send($payload);

if (!$resp->faultCode()) {

    $json = $resp->value();
    output("JSON response:<br/><pre>" . htmlspecialchars($json) . "</pre>\n");

    // manual conversion of json to php
    $value = json_decode($json, true);
    // we have to do the checking of response by ourselves (the Client only checked for http errors)
    if (is_array($value) && isset($value['id']) && $value['id'] == 1 && isset($value['result']) && is_array($value['result'])) {
        $value = $value['result'];

        output("Toolkit info:<br/>\n");
        output("<pre>");
        output("name: " . htmlspecialchars($value["toolkitName"]) . "\n");
        output("version: " . htmlspecialchars($value["toolkitVersion"]) . "\n");
        output("docs: " . htmlspecialchars($value["toolkitDocsUrl"]) . "\n");
        output("os: " . htmlspecialchars($value["toolkitOperatingSystem"]) . "\n");
        output("</pre>");
    } else {
        output("A json-rpc error occurred");
    }
} else {
    output("An http error occurred: ");
    output("Code: " . htmlspecialchars($resp->faultCode()) . " Reason: '" . htmlspecialchars($resp->faultString()) . "'\n");
}

output("</body></html>\n");
