<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_hmtx implements TableInterface
{
    public function __construct(TypeReader $reader)
    {
        $this->reader = $reader;
    }

    public function getGIDToWidth(int $numberOfHMetrics, int $numGlyphs)
    {
        $reader = $this->reader;
        $result = $reader->readUintArray(32, $numberOfHMetrics);
        $n = count($result);
        for ($i = 0; $i < $n; ++$i) {
            $result[$i] >>= 16;
        }
        $w = $result[$n - 1];
        for ($i = $n; $i < $numGlyphs; ++$i) {
            $result[] = $w;
        }
        return $result;
    }
}
