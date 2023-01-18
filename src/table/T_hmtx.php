<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_hmtx
{
    private $reader;
    private $numberOfHMetrics;
    private $numGlyphs;

    /** @var string width(2 byte) + lsb(2 byte) 組成陣列 */
    private $cache = null;

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
        $reader->seek(0);
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
     * 產生可用於 cache 的字串
     *
     * @return string
     */
    public function createCache()
    {
        $reader = $this->reader;
        $numberOfHMetrics = $this->numberOfHMetrics;
        $numGlyphs = $this->numGlyphs;
        $reader->seek(0);
        $result = [$reader->readString($numberOfHMetrics * 4)];
        $pos = ($numberOfHMetrics - 1) * 4;
        $lastWidth = substr($result[0], $pos, 2);
        for ($i = $numberOfHMetrics; $i < $numGlyphs; ++$i) {
            $result[] = $lastWidth . $reader->readString(2);
        }
        return implode('', $result);
    }

    /**
     * 讀取 cache 字串
     *
     * @param  string $cache
     * @return void
     */
    public function loadCache(string $cache)
    {
        $this->cache = $cache;
        if ((strlen($cache) >> 2) !== $this->numGlyphs) {
            throw new Exception('bad cache length');
        }
    }
    
    /**
     * 從 GID 取得 width
     *
     * @param  int $GID
     * @return int width
     */
    public function getWidth(int $GID)
    {
        if (($x = $this->getWidthAndLsb($GID)) === null) {
            return null;
        }
        return $x >> 16;
    }
   
    /**
     * 從 GID 取得 left side bearing
     *
     * @param  int $GID
     * @return int left side bearing
     */
    public function getLsb(int $GID)
    {
        if (($x = $this->getWidthAndLsb($GID)) === null) {
            return null;
        }
        $x = $x & 0xffff;
        return $x > 0x7fff ? $x - 0x10000 : $x;
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
    
    /**
     * 取得 width 與 lsb 的原始資料
     * （前 2 byte 是 width, 後 2 byte 是 lsb）
     *
     * @param  int $GID
     * @return int 32 bit unsigned
     */
    private function getWidthAndLsb(int $GID)
    {
        if ($this->cache === null) {
            $this->loadCache($this->createCache());
        }
        if ($GID < 0 || $this->numGlyphs <= $GID) {
            return null;
        }
        $pos = $GID << 2;
        return ord($this->cache[$pos]) << 24 |
            ord($this->cache[$pos + 1]) << 16 |
            ord($this->cache[$pos + 2]) << 8 |
            ord($this->cache[$pos + 3]);
    }
}
