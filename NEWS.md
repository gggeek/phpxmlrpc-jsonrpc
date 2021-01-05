JSONRPC for PHP version 1.0-alpha - unreleased

Big changes this time around!

- the library was split off from the "XML-RPC for PHP EXTRA" package
- dropped support for php < 5.3
- rebased on top of phpxmlrpc/phpxmlrpc 4.5
- fully namespaced code
- use Composer for dependency management and class autoloading
- dropped custom json parser in favour of the native one from the php engine (the custom parser lives on as polyfill-json)

PLEASE READ CAREFULLY THE NOTES BELOW to insure a smooth upgrade:

...
