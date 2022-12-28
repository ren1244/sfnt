<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\cff\INDEX;

class CharstringParser
{
    const START_OPS = [
        1 => true, 3 => true, 4 => true, 14 => true, 18 => true,
        19 => true, 20 => true, 21 => true, 22 => true, 23 => true,
    ];
    const STEM_OPS = [
        1 => true, 3 => true, 18 => true,
        19 => true, 20 => true, 23 => true,
    ];
    const MASK_OPS = [
        18 => true, 19 => true,
    ];

    public function __construct(INDEX $gSubrs, INDEX $subrs = null)
    {
        $this->gSubrs = $gSubrs;
        $gSubrBias = $gSubrs->getSubrBias();
        if ($subrs !== null) {
            $this->subrs = $subrs;
            $n = count($subrs);
            $this->subrBias = $n < 1240 ? 107 : ($n < 33900 ? 1131 : 32768);
        } else {
            $this->subrs = [];
            $n = 0;
            $this->subrBias = 107;
        }
    }

    public static function parse(
        string $charstring,
        INDEX $gSubrs,
        INDEX $subrs,
        CharstringParserEventInterface $eventHandler
    ) {
        $startFlag = false;
        $nStems = 0;
        $stack = [];
        $usedGSubrs = [];
        $usedSubrs = [];

        self::_parse(
            $charstring,
            $gSubrs,
            $subrs,
            $eventHandler,
            $startFlag,
            $nStems,
            $stack,
            $usedGSubrs,
            $usedSubrs
        );
    }

    public static function _parse(
        string $charstring,
        INDEX $gSubrs,
        INDEX $subrs,
        CharstringParserEventInterface $eventHandler,
        bool &$startFlag,
        int &$nStems,
        array &$stack,
        array &$usedGSubrs,
        array &$usedSubrs
    ) {
        $n = count($gSubrs);
        $gSubrBias = $n < 1240 ? 107 : ($n < 33900 ? 1131 : 32768);
        $n = count($subrs);
        $subrBias = $n < 1240 ? 107 : ($n < 33900 ? 1131 : 32768);
        $n = strlen($charstring);
        for ($i = 0; $i < $n; ++$i) {
            $x = ord($charstring[$i]);
            if ($x < 32) {
                if ($x === 28) { // 數字
                    $x = ord($charstring[$i + 1]) << 8 | ord($charstring[$i + 2]);
                    if ($x & 0x8000) {
                        $x -= 0x10000;
                    }
                    $eventHandler->onNumber($x, substr($charstring, $i, 3));
                    $stack[] = $x;
                    $i += 2;
                    continue;
                } elseif ($x === 12) { // 2 byte op
                    throw new Exception("Charstring Parser 尚未處理 2 byte Operators");
                    $x = ord($charstring[++$i]);
                    $eventHandler->onOperator($x, substr($charstring, $i - 1, 2), $stack);
                } else { // 1 byte op
                    if (isset(self::START_OPS[$x])) {
                        $stackLen = count($stack);
                        $nIgnore = 0;
                        if (!$startFlag && $stackLen > 2) {
                            $startFlag = true;
                        }
                        if (isset(self::STEM_OPS[$x])) {
                            $nStems += $stackLen >> 1;
                            if (isset(self::MASK_OPS[$x])) {
                                $nIgnore = $nStems + 7 >> 3;
                            }
                        }
                        $eventHandler->onOperator($x, substr($charstring, $i, 1 + $nIgnore), $stack);
                        $i += $nIgnore; // for mask
                        $stack = [];
                    } elseif ($x === 29) { // callgsubr
                        $eventHandler->onOperator($x, substr($charstring, $i, 1), $stack);
                        $subrIdx = array_pop($stack) + $gSubrBias;
                        $eventHandler->onSubr(true, false, $subrIdx);
                        $usedGSubrs[] = $subrIdx;
                        self::_parse(
                            $gSubrs[$subrIdx],
                            $gSubrs,
                            $subrs,
                            $eventHandler,
                            $startFlag,
                            $nStems,
                            $stack,
                            $usedGSubrs,
                            $usedSubrs
                        );
                        $eventHandler->onSubr(false, false, $subrIdx);
                    } elseif ($x === 10) { // callsubr
                        $eventHandler->onOperator($x, substr($charstring, $i, 1), $stack);
                        $subrIdx = array_pop($stack) + $subrBias;
                        $eventHandler->onSubr(true, true, $subrIdx);
                        $usedSubrs[] = $subrIdx;
                        self::_parse(
                            $subrs[$subrIdx],
                            $gSubrs,
                            $subrs,
                            $eventHandler,
                            $startFlag,
                            $nStems,
                            $stack,
                            $usedGSubrs,
                            $usedSubrs
                        );
                        $eventHandler->onSubr(false, true, $subrIdx);
                    } else {
                        $eventHandler->onOperator($x, substr($charstring, $i, 1), $stack);
                        $stack = [];
                    }
                }
            } else {
                if ($x < 247) {
                    $x = $x - 139;
                    $eventHandler->onNumber($x, substr($charstring, $i, 1));
                } elseif ($x < 251) {
                    $w = ord($charstring[++$i]);
                    $x = ($x - 247) * 256 + $w + 108;
                    $eventHandler->onNumber($x, substr($charstring, $i - 1, 2));
                } elseif ($x < 255) {
                    $w = ord($charstring[++$i]);
                    $x = - (($x - 251) * 256) - $w - 108;
                    $eventHandler->onNumber($x, substr($charstring, $i - 1, 2));
                } else {
                    if ($i + 4 >= $n) {
                        throw new Exception('資料長度不足以讀取');
                    }
                    $x = ord($charstring[$i + 1]) << 24 | ord($charstring[$i + 2]) << 16 | ord($charstring[$i + 3]) << 8 | ord($charstring[$i + 4]);
                    if ($x & 0x80000000) {
                        $x -= 0x100000000;
                    }
                    $eventHandler->onNumber($x, substr($charstring, $i, 5));
                    $i += 4;
                }
                $stack[] = $x;
            }
        }
    }
}
