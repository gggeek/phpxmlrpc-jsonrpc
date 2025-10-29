<?php
require_once __DIR__ . "/_prepend.php";

output('<html lang="en">
<head><title>phpjsonrpc - Notification demo</title></head>
<body>
<h1>Notification demo</h1>
<h2>Send a notification to the server</h2>
');

use PhpXmlRpc\JsonRpc\Client;
use PhpXmlRpc\JsonRpc\Notification;
use PhpXmlRpc\JsonRpc\Value;

// "abuse" of the API: we call a method known to return a value, but ask the server to discard the output...
$notification = new Notification('examples.stringecho', array(new Value('This string will not be echoed back')));

$client = new Client(JSONRPCSERVER);
$response = $client->send($notification);
if ($response === true) {
    output("Notification sent");
} else {
    output("Error sending the notification:<br/><pre>" . htmlspecialchars($response->faultString()) . "</pre>\n");
}
