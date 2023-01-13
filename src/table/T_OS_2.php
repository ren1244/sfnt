<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_OS_2 implements TableInterface
{
    public $sTypoAscender;
    public $sTypoDescender;
    public $sTypoLineGap;
    public $sCapHeight;

    public function __construct(TypeReader $reader)
    {
        $version = $reader->readUint(16);
        $reader->ignore(66);
        $this->sTypoAscender = $reader->readInt(16);
        $this->sTypoDescender = $reader->readInt(16);
        $this->sTypoLineGap = $reader->readInt(16);
        if ($version > 1) {
            $reader->ignore(14);
            $this->sCapHeight = $reader->readInt(16);
        }
    }
}
