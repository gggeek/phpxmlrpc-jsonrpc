<?php

include_once __DIR__ . '/WebTestCase.php';

/**
 * Tests for php files in the 'extras' directory.
 */
class ExtraFilesTest extends PhpXmlRpc_WebTestCase
{
    public function testBenchmark()
    {
        $page = $this->request('?extras=benchmark.php');
    }
}
