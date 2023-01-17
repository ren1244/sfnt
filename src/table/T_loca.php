<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_loca
{
    private $reader;
    private $version;
    private $positionArray;
    private $totalLength;

    public function __construct(?TypeReader $reader = null, ?T_head $head = null)
    {
        $this->reader = $reader;
        if($head !== null) {
            $this->version = $head->indexToLocFormat;
        }
    }
    
    /**
     * 設定 version
     * version 應由 head table 的 indexToLocFormat 取得
     *
     * @deprecated 從 v1.1.0 開始，version 會自動載入，不用再手動設定
     * @param  int $version
     * @return void
     */
    public function setVersion(int $version)
    {
        // $this->version = $version;
    }
    
    /**
     * 取得 GID 對應的 glyf 位置
     * 使用前須先用 setVersion 設定 version
     *
     * @param  int $GID
     * @param  int $start
     * @param  int $end
     * @return void
     */
    public function getGlyfPosition(int $GID, int &$start, int &$end)
    {
        $reader = $this->reader;
        if ($this->version === 1) {
            // long offsets
            $reader->seek($GID << 2);
            $start = $reader->readUint(32);
            $end = $reader->readUint(32);
        } elseif ($this->version === 0) {
            // short offsets
            $reader->seek($GID << 1);
            $start = $reader->readUint(16);
            $end = $reader->readUint(16);
        } else {
            throw new Exception('Unknow version of loca');
        }
    }

    public function prepareSubset()
    {
        $this->positionArray = [$this->totalLength = 0];
    }

    public function push(int $length)
    {
        $this->positionArray[] = ($this->totalLength += $length);
    }

    public function subset()
    {
        return pack('N*', ...$this->positionArray);
    }
}
