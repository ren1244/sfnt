<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_hmtx
{
    private $reader;
    private $numberOfHMetrics;
    private $numGlyphs;

    public function __construct(TypeReader $reader, T_hhea $hhea, T_maxp $maxp)
    {
        $this->reader = $reader;
        $this->numberOfHMetrics = $hhea->numberOfHMetrics;
        $this->numGlyphs = $maxp->numGlyphs;
    }
    
    /**
     * getGIDToWidth
     *
     * @param  int|null $numberOfHMetrics 原始的 numberOfHMetrics
     * @param  int|null $numGlyphs 原始的 numGlyphs
     * @return array
     * @since 1.1.0 numberOfHMetrics 與 numGlyphs 不需指定，改由內部自動取得
     */
    public function getGIDToWidth(int $numberOfHMetrics = 0, int $numGlyphs = 0)
    {
        $reader = $this->reader;
        $numberOfHMetrics = $this->numberOfHMetrics;
        $numGlyphs = $this->numGlyphs;
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
     * @param  int|null $numberOfHMetrics 原始的 numberOfHMetrics
     * @param  int|null $numGlyphs 原始的 numGlyphs
     * @return string
     * @since 1.1.0 numberOfHMetrics 與 numGlyphs 不需指定，改由內部自動取得
     */
    public function subset(array $usedGID, int $numberOfHMetrics = 0, int $numGlyphs = 0)
    {
        $reader = $this->reader;
        $numberOfHMetrics = $this->numberOfHMetrics;
        $numGlyphs = $this->numGlyphs;
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
