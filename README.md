JSON-RPC for PHP (a.k.a. PHPJSONRPC)
====================================

A php library for building json-rpc clients and servers.

Originally bundled as part of the [phpxmlrpc/extras](https://github.com/gggeek/phpxmlrpc-extras) package.

At the moment it only partially supports version 2.0 of the JSON-RPC protocol.
Features still to be implemented are: multicall, notifications, standard error codes.

Requirements and Installation
-----------------------------

* PHP >= 5.4.0
* PHP Json extension
* phpxmlrpc/phpxmlrpc >= 4.10.1

The recommended way to install this library is using Composer.

Documentation
-------------

* See the documentation page at [gggeek.github.io/phpxmlrpc-jsonrpc](https://gggeek.github.io/phpxmlrpc-jsonrpc)
  for a list of the library main features and all project related information, including information about online resources such as
  debuggers and demo servers.

* Automatically-generated documentation for the API is available online at [http://gggeek.github.io/phpxmlrpc-jsonrpc/doc/api/index.html](http://gggeek.github.io/phpxmlrpc-jsonrpc/doc/api/index.html)

* You are encouraged to look also at the code examples found in the demo/ directory.

  Note: to reduce the size of the download, the demo files are not part of the default package installed with Composer.
  You can either check them out online at https://github.com/gggeek/phpxmlrpc-jsonrpc/tree/master/demo, download them as a separate
  tarball from https://github.com/gggeek/phpxmlrpc-jsonrpc/releases or make sure they are available locally by installing the
  library using Composer option `--prefer-install=source`. Whatever the method chosen, make sure that the demo folder is
  not directly accessible from the internet, i.e. it is not within the webserver root directory).

Extras
------

* This library does include a visual debugger which can be used to troubleshoot connections to 3rd party xml-rpc servers.
  In case you'd like to use the debugger but do not have a working PHP installation, you can run it standalone as a
  Container image. Instructions can be found at https://github.com/gggeek/phpxmlrpc-debugger

* A companion PHP library, which adds support for JSON-RPC servers to automatically generate API documentation, and more,
  is available at https://github.com/gggeek/phpxmlrpc-extras

* Last but not least, a Javascript library, implementing both XML-RPC and JSON-RPC clients using a very similar API, is
  available at https://github.com/gggeek/jsxmlrpc

License
-------
Use of this software is subject to the terms in the [license.txt](license.txt) file

[![License](https://poser.pugx.org/phpxmlrpc/jsonrpc/license)](https://packagist.org/packages/phpxmlrpc/jsonrpc)
[![Latest Stable Version](https://poser.pugx.org/phpxmlrpc/jsonrpc/v/stable)](https://packagist.org/packages/phpxmlrpc/jsonrpc)
[![Total Downloads](https://poser.pugx.org/phpxmlrpc/jsonrpc/downloads)](https://packagist.org/packages/phpxmlrpc/jsonrpc)

[![Build Status](https://github.com/gggeek/phpxmlrpc-jsonrpc/actions/workflows/ci.yaml/badge.svg)](https://github.com/gggeek/phpxmlrpc-jsonrpc/actions/workflows/ci.yaml)
[![Code Coverage](https://codecov.io/gh/gggeek/phpxmlrpc-jsonrpc/branch/master/graph/badge.svg)](https://app.codecov.io/gh/gggeek/phpxmlrpc-jsonrpc)
