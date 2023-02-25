<?php

namespace PhpXmlRpc\JsonRpc\Helper;

use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Traits\LoggerAware;

/**
 * @todo implement an Interface
 */
class Charset
{
    use LoggerAware;

    protected $ecma262_iso88591_Entities = array();

    protected static $instance = null;

    /**
     * This class is singleton for performance reasons.
     * @todo can't we just make $xml_iso88591_Entities a static variable instead ?
     *
     * @return Charset
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Force usage as singleton.
     */
    protected function __construct()
    {
    }

    /**
     * @internal this function will become protected in the future
     * @return array[]
     */
    public function buildConversionTable()
    {
        if (!$this->ecma262_iso88591_Entities) {
            $this->ecma262_iso88591_Entities['in'] = array();
            $this->ecma262_iso88591_Entities['out'] = array();
            for ($i = 0; $i < 32; $i++) {
                $this->ecma262_iso88591_Entities['in'][] = chr($i);
                $this->ecma262_iso88591_Entities['out'][] = sprintf('\u%\'04x', $i);
            }
            for ($i = 160; $i < 256; $i++) {
                $this->ecma262_iso88591_Entities['in'][] = chr($i);
                $this->ecma262_iso88591_Entities['out'][] = sprintf('\u%\'04x', $i);
            }
        }

        return $this->ecma262_iso88591_Entities;
    }

    /**
     * Encode php strings to valid JSON unicode representation.
     * All chars outside ASCII range are converted to \uXXXX for maximum portability by default.
     *
     * @param string $data
     * @param string $srcEncoding charset of source string, defaults to PhpXmlRpc::$xmlrpc_internalencoding
     * @param string $destEncoding charset of the encoded string, defaults to ASCII for maximum interoperability
     * @return string
     */
    public function encodeEntities($data, $srcEncoding = '', $destEncoding = '')
    {
        if ($srcEncoding == '') {
            // lame, but we know no better...
            $srcEncoding = PhpXmlRpc::$xmlrpc_internalencoding;
        }

        if ($destEncoding == '') {
            $destEncoding = 'US-ASCII';
        }

        // in case there is transcoding going on, let's upscale to UTF8
        if (!in_array($srcEncoding, array('UTF-8', 'ISO-8859-1', 'US-ASCII')) && $srcEncoding != $destEncoding &&
            function_exists('mb_convert_encoding')) {
            $data = mb_convert_encoding($data, 'UTF-8', str_replace('US-ASCII', 'ASCII', $srcEncoding));
            $srcEncoding = 'UTF-8';
        }

        // list ordered with (expected) most common scenarios first
        switch (strtoupper($srcEncoding . '_' . $destEncoding)) {
            case 'UTF-8_UTF-8':
            case 'ISO-8859-1_ISO-8859-1':
            case 'US-ASCII_UTF-8':
            case 'US-ASCII_US-ASCII':
            case 'US-ASCII_ISO-8859-1':
                $escapedData = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
                break;

            case 'UTF-8_US-ASCII':
            case 'UTF-8_ISO-8859-1':
                // NB: this will choke on invalid UTF-8, going most likely beyond EOF
                $escapedData = "";
                // be kind to users creating string jsonrpcvals out of different php types
                $data = (string)$data;
                $ns = strlen($data);
                for ($nn = 0; $nn < $ns; $nn++) {
                    $ch = $data[$nn];
                    $ii = ord($ch);
                    // 1 - 7 bits: 0bbbbbbb (127)
                    if ($ii < 128) {
                        /// @todo shall we replace this with a (supposedly) faster str_replace?
                        switch ($ii) {
                            case 8:
                                $escapedData .= '\b';
                                break;
                            case 9:
                                $escapedData .= '\t';
                                break;
                            case 10:
                                $escapedData .= '\n';
                                break;
                            case 11:
                                $escapedData .= '\v';
                                break;
                            case 12:
                                $escapedData .= '\f';
                                break;
                            case 13:
                                $escapedData .= '\r';
                                break;
                            case 34:
                                $escapedData .= '\"';
                                break;
                            case 47:
                                $escapedData .= '\/';
                                break;
                            case 92:
                                $escapedData .= '\\\\';
                                break;
                            default:
                                $escapedData .= $ch;
                        } // switch
                    } // 2 - 11 bits: 110bbbbb 10bbbbbb (2047)
                    else if ($ii >> 5 == 6) {
                        $b1 = ($ii & 31);
                        $ii = ord($data[$nn + 1]);
                        $b2 = ($ii & 63);
                        $ii = ($b1 * 64) + $b2;
                        $ent = sprintf('\u%\'04x', $ii);
                        $escapedData .= $ent;
                        $nn += 1;
                    } // 3 - 16 bits: 1110bbbb 10bbbbbb 10bbbbbb
                    else if ($ii >> 4 == 14) {
                        $b1 = ($ii & 15);
                        $ii = ord($data[$nn + 1]);
                        $b2 = ($ii & 63);
                        $ii = ord($data[$nn + 2]);
                        $b3 = ($ii & 63);
                        $ii = ((($b1 * 64) + $b2) * 64) + $b3;
                        $ent = sprintf('\u%\'04x', $ii);
                        $escapedData .= $ent;
                        $nn += 2;
                    } // 4 - 21 bits: 11110bbb 10bbbbbb 10bbbbbb 10bbbbbb
                    else if ($ii >> 3 == 30) {
                        $b1 = ($ii & 7);
                        $ii = ord($data[$nn + 1]);
                        $b2 = ($ii & 63);
                        $ii = ord($data[$nn + 2]);
                        $b3 = ($ii & 63);
                        $ii = ord($data[$nn + 3]);
                        $b4 = ($ii & 63);
                        $ii = ((((($b1 * 64) + $b2) * 64) + $b3) * 64) + $b4;
                        $ent = sprintf('\u%\'04x', $ii);
                        $escapedData .= $ent;
                        $nn += 3;
                    }
                }
                break;

            case 'ISO-8859-1_UTF-8':
                $escapedData = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
                $escapedData = utf8_encode($escapedData);
                break;

            case 'ISO-8859-1_US-ASCII':
                $this->buildConversionTable();
                $escapedData = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
                $escapedData = str_replace($this->ecma262_iso88591_Entities['in'], $this->ecma262_iso88591_Entities['out'], $escapedData);
                break;

            default:
                if (function_exists('mb_convert_encoding')) {
                    // If reaching where, there are only 2 cases possible: UTF8->XXX or XXX->XXX
                    if ($srcEncoding === 'UTF-8') {
                        $data = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
                    }
                    if ($srcEncoding !== $destEncoding) {
                        try {
                            // php 7.4 and lower: a warning is generated. php 8.0 and up: an Error is thrown. So much for BC...
                            $data = @mb_convert_encoding($data, str_replace('US-ASCII', 'ASCII', $destEncoding), str_replace('US-ASCII', 'ASCII', $srcEncoding));
                        } catch (\ValueError $e) {
                            $data = false;
                        }
                    }
                    if ($data === false) {
                        $escapedData = '';
                        $this->getLogger()->error('XML-RPC: ' . __METHOD__ . ": Converting from $srcEncoding to $destEncoding via mbstring: failed...");
                    } else {
                        if ($srcEncoding === 'UTF-8') {
                            $escapedData = $data;
                        } else {
                            /// @todo cache this conversion table
                            $search = array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12));
                            foreach ($search as $char) {
                                $replace[] = mb_convert_encoding($char, str_replace('US-ASCII', 'ASCII', $destEncoding), 'ASCII');
                            }
                            $escapedData = str_replace($search, $replace, $data);
                        }
                    }
                } else {
                    $escapedData = '';
                    $this->getLogger()->error('XML-RPC: ' . __METHOD__ . ": Converting from $srcEncoding to $destEncoding: not supported...");
                }
        } // switch

        return $escapedData;
    }
}
