<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_name
{
    private $nameRecord = [];

    public function __construct(TypeReader $reader)
    {
        $version = $reader->readUint(16);
        $count = $reader->readUint(16);
        $storageOffset = $reader->readOffset(16);
        $nameRecordOffset = $reader->getIdx();
        if ($version === 1) {
            $langTagCount = $reader->readUint(16);
            $reader->ignore($langTagCount * 4);
        }
        // 依照 platId, encId 分類, 
        for ($i = 0; $i < $count; ++$i) {
            $reader->seek($nameRecordOffset + $i * 12);
            $arr = $reader->readUintArray(16, 6);
            $key = "$arr[0]-$arr[1]";
            if (!isset($this->nameRecord[$key])) {
                $this->nameRecord[$key] = [];
            }
            $reader->seek($storageOffset + $arr[5]);
            $str = $reader->readString($arr[4]);
            $this->nameRecord[$key][$arr[3]] = mb_convert_encoding($str, 'UTF-8', 'UTF-16BE');
        }
    }

    public function getNames(int $platId, int $encId)
    {
        $key = "$platId-$encId";
        return $this->nameRecord[$key] ?? null;
    }
}
