## JSON-RPC for PHP version XX (unreleased)

- improved: added CI testing on php 8.4. Default the local testing container to using PHP 8.1 on Ubuntu Jammy


## JSON-RPC for PHP version 1.0-beta2 - 2024/4/15

- bumped the minimum required version of php to 5.4

- bumped the minimum required version of phpxmlrpc/phpxmlrpc to 4.10.1, fixing the `Client->call` method sometimes
  returning an xml-rpc response instead of a json-rpc one

- there is support for extra character sets than UTF-8/ISO-8859-1/ASCII when the php `mbstring` extension is installed,
  both as internal application charset and as received payload

- fixed: a "null" value was not considered a valid response

- fixed generation of comments server-side and parsing them client-side

- added method `PhpJsonRpc::setLogger` to allow overtaking the logger for all JsonRpc classes

- made all error messages go through the Logger facility instead of calling directly `error_log`

- made sure php warnings in method handlers do not disrupt the server even when it has debug level 0 and 1

- multiple fixes to accommodate changes in the phpxmlrpc to 4.10 API, including support for server-side per-method-handler
  parameter-types declarations and adding `httpResponse` data to parsed Responses

- fixed one warning with php 8.2 when running a Server

- fixed encoding of DateTime objects with php 5.4 in `Encoder::encode`

- prefer emitting "Content-type: application/json" to "Content-type: application/json; charset=UTF-8", as per the current RFCs

- fixes to demo files and benchmark.php

- run CI tests also on php 8.3

- BC notes:

  - the `Parser::parseRequest` and `Parser::parseResponse` now accept (and are called with) a different set of arguments;
    they also return an array instead of `true` upon success


## JSON-RPC for PHP version 1.0-beta1 - 2022/12/20

- bumped the minimum required version of phpxmlrpc/phpxmlrpc to 4.9.2


## JSON-RPC for PHP version 1.0-alpha - 2021/1/5

Big changes this time around!

- the library was split off from the "XML-RPC for PHP EXTRA" package
- dropped support for php < 5.3
- rebased on top of phpxmlrpc/phpxmlrpc 4.5
- fully namespaced code
- use Composer for dependency management and class autoloading
- dropped the custom json parser in favour of the native one from the php engine (the custom parser lives on as polyfill-json)
- added a ready-to-use GUI debugger and some example code

PLEASE READ CAREFULLY THE NOTES BELOW to insure a smooth upgrade:

A compatibility layer is provided for users who were using previous versions of this library, and want to upgrade to
the latest version without making changes to their existing code; it consists of files `lib/jsonrpc.inc` and `lib/jsonrpcs.inc`.

When using it, the known changes compared to the previous API version are:
* since we now rely on the PHP native function to decode json, some json strings will be rejected as invalid which
  where previously accepted. F.e. strings containing javascript-like comments
* functions `jsonrpc_parse_req` and `jsonrpc_parse_resp` now default to TRUE for their 3rd parameterm `$use_extension`.
  Also, passing in FALSE will have no effect at all.
* function `php_jsonrpc_decode_json` is less strict in determining when the parsed json string represents a Request or
  Response object - it will accept them even if they have extra members
* the class hierarchy is now different, eg. class `jsonrpc_client` now inherits from `\PhpXmlRpc\JsonRpc\Client` which
  inherits from `\PhpXmlRpc\Client`. It does not inherit any more from `xmlrpc_client`
* when using a `PhpXmlRpc\JsonRpc\Client` instead of a `jsonrpc_client`, the requests will be serialized using plain utf-8
  encoded text, instead of escaping every non-ascii character with its unicode numerical representation
* the `json_extension_api.inc` file has been removed. The old json parser is in the process of being reimplemented in
  its own Composer-installable package: phpxmlrpc/polyfill-json
* a lot of improvements and bugfixes coming from the underlying phpxmlrpc library. You are encouraged to read the
  'API Changes' doc available on github at https://github.com/gggeek/phpxmlrpc/blob/master/doc/api_changes_v4.md
  and the notes for release 4.0.0 in the NEWS file at https://github.com/gggeek/phpxmlrpc/blob/master/NEWS#L164
