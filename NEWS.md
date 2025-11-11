## JSON-RPC for PHP version 1.0.1 - 2025/11/11

- fixed: the `_prepend.php` file used by the demos would not locate the php autoloader when the library is installed as
  dependency

- fixed: setting a custom Parser or CharsetEncoder to the JsonRpc Value, Request, Response and Server classes does not
  reset anymore the same for the corresponding XmlRpc parent class, and vice-versa. This makes it possible to freely
  mix and match json-rpc and xml-rpc within the same php script.
  NB: this can have an impact in you access directly protected static members `$parser` and `$charsetEncoder.`

- improved: make it easy to allow CORS requests to the demo server on hosts other than the altervista one

- improved: moved the public demo server and debugger from altervista.org to tanoconsulting.com


## JSON-RPC for PHP version 1.0 - 2025/10/30

- new: default the code to use json-rpc version 2.0 protocol, while allowing usage of json-rpc 1.0 too.

  The easiest way to switch everything to keep using version 1.0 is to add a call
  `PhpJsonRpc::$defaultJsonrpcVersion = PhpJsonRpc::VERSION_1_0;` at the beginning of your code

- new: added a `Notification` class, to be used for sending json-rpc notification calls

- breaking change: when creating a Request, passing in a NULL id will now automatically generate a unique id.
  To manually create notifications, use the new `Notification` class instead.
  Note that this change does not apply if using the legacy `jsonrpcmsg` class
  The "id" of both Request and Response objects has been made protected. To access it, use the `id()` method.

- breaking change: the Server will now respond to Notifications (request with no Id / null Id) with an HTTP 204
  response with no body

- breaking change: when sending a Notification call, `Client::send()` will now return true instead of a Response object,
  iff the server returns an empty http response body

- breaking change: when a Client sends invalid json, the returned response will sport a faultCode of 100+X, with X
  corresponding to the value returned by php function `json_last_error`, eg. 104 for no data, instead of previous 5

- breaking change: `Parser::parseRequest()` and `Parser::parseResponse()` always return an array, even on failure.
  Also, direct access to `$parser->_xh` is deprecated

- breaking change: classes `Request` and `Response` method and disallow access to the `$id` member. They gained an
  accessor method `id()` to retrieve the id

- breaking change: `Serializer::serializeRequest()` and `Serializer::serializeResponse()` have had the order of their
  arguments changed

- fixed: when receiving empty requests, the returned response's error code is now the same on php 5 as it is on
  later php versions

- fixed: `PhpJsonRpc::setLogger()` was not injecting the logger into the `Serializer` class

- fixed: removed warnings when running on php 8.5

- improved: it is now possible to set the Server option `OPT_DEBUG_FORMAT` to value 'extra_member', to have the debug
  info (emitted when the server debug level is > 2) serialized as an extra member in the response, rather than as
  a js comment, which requires a json5 parser or this library's client

- improved: the data returned by json-rpc method "interop.whichToolkit" now reports info about this package instead
  of info related to phpxmlrpc

- improved: added demos for client-side usage, as well as demos for symfony integration of both client and server

- improved: support for `Value` objects of type 'dateTime.iso8601' - even though datetimes are not part of JSON-RPC or JSON

- improved: more warnings are emitted in unexpected/unsupported scenarios (to the php error log by default)

- improved: added CI testing on php 8.4 and 8.5. Default the local testing container to using PHP 8.1 on Ubuntu Jammy

- bumped the minimum required version of phpxmlrpc/phpxmlrpc to 4.11.4

- other API changes:
  - classes `Client`, `Request` and `Response` gained methods `getJsonRcVersion` and `setjsonRpcVersion`
  - class `Request` has gained a 5th constructor argument: `$jsonrpcVersion = null`
  - class `Request` has gained a method: `getParamName($i)`, useful for dealing with named-arguments requests.
    Also, its method `addParam` gained a 2nd argument: `$name=null`
  - class `Request` has gained a protected method: `generateId()`
  - visibility of `Request::$content_type` has been lowered from public to protected
  - class `Response` has gained a methods `isFromServer` and `setIsFromServer($value)`
  - method `Server::execute()` has gained a 5th param: `$jsonrpcVersion = null`
  - class `Server` now overrides more of the parent's methods
  - member `client->$no_multicall` defaults to `null`, as the support for multicall calls depends on the json-rpc version
    in use (version 2.0 does support it via "batch" calls)
  - member `$parser->_xh` has gained new elements
  - class `Wrapper` gained methods `wrapJsonrpcServer` and `wrapJsonrpcMethod` as aliases for existing methods, as well
    as members `$prefix` and `$allowedResponseClass`

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
