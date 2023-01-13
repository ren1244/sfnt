<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_hmtx implements TableInterface
{
    private $reader;

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

    /**
     * subset
     *
     * @param  array $usedGID 要取子集的 GID
     * @param  int $numberOfHMetrics 原始的 numberOfHMetrics
     * @param  int $numGlyphs 原始的 numGlyphs
     * @return string
     */
    public function subset(array $usedGID, int $numberOfHMetrics, int $numGlyphs)
    {
        $reader = $this->reader;
        // GID = 0
        $reader->seek(0);
        $result = [$reader->readString(4)];
        // 最後一個 Width
        $reader->seek($numberOfHMetrics - 1 << 2);
        $lastWidth = $reader->readString(2);
        // 使用到的 GID 的 width 與 lsb
        foreach ($usedGID as $GID => $x) {
            if ($GID < $numberOfHMetrics) {
                $reader->seek($GID << 2);
                $result[] = $reader->readString(4);
            } elseif ($GID < $numGlyphs) {
                // 偏移量等同: $numberOfHMetrics * 4 + ($GID - $numberOfHMetrics) * 2
                $reader->seek($numberOfHMetrics + $GID << 1);
                $result[] = $lastWidth . $reader->readString(2);
            } else {
                throw new Exception('GID >= numGlyphs');
            }
        }
        return implode('', $result);
    }
}
