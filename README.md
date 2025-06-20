JSON-RPC for PHP (a.k.a. PHPJSONRPC)
====================================

A php library for building json-rpc clients and servers.

Originally bundled as part of the [phpxmlrpc/extras](https://github.com/gggeek/phpxmlrpc-extras) package.

At the moment it only (partially) supports version 1.0 of the JSON-RPC protocol.
Features still to be implemented are: multicall, notifications, peer-to-peer communication.

Main features
-------------
* Support for creating both jsonrpc clients and servers
* Support for http features including compression of both requests and responses, cookies, proxies, basic auth, https, ntlm auth and keepalives with the php cURL extension
* Optional validation of parameter types of incoming jsonrpc request
* Possibility to register existing php function or class methods as webservices, extracting value-added information from phpdoc comments
* Support for system.listMethods, system.methodHelp, system.multicall and system.getCapabilities methods
* Support for UTF8, Latin-1 and ASCII character encodings. With the php mbstring extension enabled, even more character sets are supported.
* A web based visual debugger is included with the library

Requirements
------------

* PHP >= 5.4.0
* PHP Json extension
* phpxmlrpc/phpxmlrpc >= 4.10.1

Installation
------------

Via Composer

License
-------
Use of this software is subject to the terms in the [license.txt](license.txt) file

[![License](https://poser.pugx.org/phpxmlrpc/jsonrpc/license)](https://packagist.org/packages/phpxmlrpc/jsonrpc)
[![Latest Stable Version](https://poser.pugx.org/phpxmlrpc/jsonrpc/v/stable)](https://packagist.org/packages/phpxmlrpc/jsonrpc)
[![Total Downloads](https://poser.pugx.org/phpxmlrpc/jsonrpc/downloads)](https://packagist.org/packages/phpxmlrpc/jsonrpc)

[![Build Status](https://github.com/gggeek/phpxmlrpc-jsonrpc/actions/workflows/ci.yaml/badge.svg)](https://github.com/gggeek/phpxmlrpc-jsonrpc/actions/workflows/ci.yaml)
[![Code Coverage](https://codecov.io/gh/gggeek/phpxmlrpc-jsonrpc/branch/master/graph/badge.svg)](https://app.codecov.io/gh/gggeek/phpxmlrpc-jsonrpc)
