<?php

/**
 * @todo rename both the class and the file
 */
class JsonrpcArgParser
{
    /**
     * @return array
     * @todo check how to grab the parameters from phpunit config
     */
    public static function getArgs()
    {
        /// @todo should we prefix all test parameters with TESTS_ ?
        $args = array(
            'DEBUG' => 0,
            'HTTPSERVER' => 'localhost',
            'HTTPPREFIX' => null,
            // now that we run tests in Docker by default, with a webserver set up for https, let's default to it
            'HTTPSSERVER' => 'localhost',
            'HTTPSPREFIX' => null,
            // example alternative:
            //'HTTPSSERVER' => 'gggeek.altervista.org',
            //'HTTPSPREFIX' => '/sw/phpxmlrpc/extras',
            'HTTPSIGNOREPEER' => false,
            'HTTPSVERIFYHOST' => 2,
            'SSLVERSION' => 0,
        );

        // check for params passed as env vars
        foreach ($args as $key => $val) {
            if (array_key_exists($key, $_SERVER)) {
                $args[$key] = $_SERVER[$key];
            }
        }

        return $args;
    }
}
