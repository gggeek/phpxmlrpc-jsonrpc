<?php


namespace PhpXmlRpc\JsonRpc\Helper;

class Parser
{
    public $_xh = array(
        'ac' => '',
        'isf' => 0,
        'isf_reason' => '',
        'method' => false,
        'params' => array(),
        'pt' => array(),
        'rt' => '',
    );

    /**
     * Parse a json string, expected to be jsonrpc request format
     */
    public function parseRequest($data, $return_phpvals = false, $use_extension = false, $src_encoding = '')
    {
        $this->_xh['isf'] = 0;
        $this->_xh['isf_reason'] = '';
        $this->_xh['pt'] = array();
        if ($return_phpvals && $use_extension) {
            $ok = json_parse_native($data);
        } else {
            $ok = json_parse($data, $return_phpvals, $src_encoding);
        }
        if ($ok) {
            if (!$return_phpvals)
                $this->_xh['value'] = @$this->_xh['value']->me['struct'];

            if (!is_array($this->_xh['value']) || !array_key_exists('method', $this->_xh['value'])
                || !array_key_exists('params', $this->_xh['value']) || !array_key_exists('id', $this->_xh['value'])
            ) {
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct jsonrpc request object';
                return false;
            } else {
                $this->_xh['method'] = $this->_xh['value']['method'];
                $this->_xh['params'] = $this->_xh['value']['params'];
                $this->_xh['id'] = $this->_xh['value']['id'];
                if (!$return_phpvals) {
                    /// @todo we should check for appropriate type for method name and params array...
                    $this->_xh['method'] = $this->_xh['method']->scalarval();
                    $this->_xh['params'] = $this->_xh['params']->me['array'];
                    $this->_xh['id'] = php_jsonrpc_decode($this->_xh['id']);
                } else {
                    // to allow 'phpvals' type servers to work, we need to rebuild $this->_xh['pt'] too
                    foreach ($this->_xh['params'] as $val) {
                        // since we rebuild this after converting json values to php,
                        // we've lost the info about array/struct, and we try to rebuild it
                        /// @bug empty objects will be recognized as empty arrays
                        /// @bug an object with keys '0', '1', ... 'n' will be recognized as an array
                        $typ = gettype($val);
                        if ($typ == 'array' && count($val) && count(array_diff_key($val, array_fill(0, count($val), null))) !== 0) {
                            $typ = 'object';
                        }
                        $this->_xh['pt'][] = php_2_jsonrpc_type($typ);
                    }
                }
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Parse a json string, expected to be in json-rpc response format.
     * @todo checks missing:
     *       - no extra members in response
     *       - no extra members in error struct
     *       - resp. ID validation
     */
    public function parseResponse($data, $return_phpvals = false, $use_extension = false, $src_encoding = '')
    {
        $this->_xh['isf'] = 0;
        $this->_xh['isf_reason'] = '';
        if ($return_phpvals && $use_extension) {
            $ok = json_parse_native($data);
        } else {
            $ok = json_parse($data, $return_phpvals, $src_encoding);
        }
        if ($ok) {
            if (!$return_phpvals) {
                $this->_xh['value'] = @$this->_xh['value']->me['struct'];
            }
            if (!is_array($this->_xh['value']) || !array_key_exists('result', $this->_xh['value'])
                || !array_key_exists('error', $this->_xh['value']) || !array_key_exists('id', $this->_xh['value'])
            ) {
                //$this->_xh['isf'] = 2;
                $this->_xh['isf_reason'] = 'JSON parsing did not return correct jsonrpc response object';
                return false;
            }
            if (!$return_phpvals) {
                $d_error = php_jsonrpc_decode($this->_xh['value']['error']);
                $this->_xh['value']['id'] = php_jsonrpc_decode($this->_xh['value']['id']);
            } else {
                $d_error = $this->_xh['value']['error'];
            }
            $this->_xh['id'] = $this->_xh['value']['id'];
            if ($d_error != null) {
                $this->_xh['isf'] = 1;

                //$this->_xh['value'] = $d_error;
                if (is_array($d_error) && array_key_exists('faultCode', $d_error)
                    && array_key_exists('faultString', $d_error)
                ) {
                    if ($d_error['faultCode'] == 0) {
                        // FAULT returned, errno needs to reflect that
                        $d_error['faultCode'] = -1;
                    }
                    $this->_xh['value'] = $d_error;
                }
                // NB: what about jsonrpc servers that do NOT respect
                // the faultCode/faultString convention???
                // we force the error into a string. regardless of type...
                else //if (is_string($this->_xh['value']))
                {
                    if ($return_phpvals) {
                        $this->_xh['value'] = array('faultCode' => -1, 'faultString' => var_export($this->_xh['value']['error'], true));
                    } else {
                        $this->_xh['value'] = array('faultCode' => -1, 'faultString' => serialize_jsonrpcval($this->_xh['value']['error']));
                    }
                }

            } else {
                $this->_xh['value'] = $this->_xh['value']['result'];
            }
            return true;

        } else {
            return false;
        }
    }
}
