<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\TypeReader;

class Charset
{
    public function __construct(TypeReader $reader, int $start, int $nGlyphs)
    {
        $reader->seek($start);
        $this->format = $reader->readUint(8);
        switch ($this->format) {
            case 0:
                $arr = $reader->readUintArray(16, $nGlyphs - 1);
                break;
            case 1:
                $arr = [];
                $count = 0;
                while ($count < $nGlyphs - 1) {
                    $sid = $reader->readUint(16);
                    $len = $reader->readUint(8);
                    $count += $len + 1;
                    for ($i = 0; $i <= $len; ++$i) {
                        $arr[] = $sid + $i;
                    }
                }
                break;
            case 2:
                $arr = [];
                $count = 0;
                while ($count < $nGlyphs - 1) {
                    $sid = $reader->readUint(16);
                    $len = $reader->readUint(16);
                    $count += $len + 1;
                    for ($i = 0; $i <= $len; ++$i) {
                        $arr[] = $sid + $i;
                    }
                }
                break;
            default:
                throw new Exception('Unknow format of charset');
        }
        $this->GIDToCID = $arr;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function getCID(int $GID)
    {
        return isset($this->GIDToCID[$GID - 1]) ? $this->GIDToCID[$GID - 1] : null;
    }
}
