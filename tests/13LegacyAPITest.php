<?php

include_once __DIR__ . '/ServerAwareTestCase.php';

/**
 * Long-term, this should replace all testing of the legacy API done via the main test-suite...
 */
class LegacyAPITest extends PhpJsonRpc_ServerAwareTestCase
{
    public function testLegacyLoader()
    {
        /// @todo pass on as cli args for the executed script all the args that are already parsed by now

        exec('php ' . __DIR__ . '/legacy_loader_test.php', $out, $result);

        /// @todo dump output if in debug mode or if test fails

        $this->assertEquals(0, $result);
    }
}
