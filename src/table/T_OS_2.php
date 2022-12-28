<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_OS_2 implements TableInterface
{
    public function __construct(TypeReader $reader)
    {
        $reader->ignore(68);
        $this->sTypoAscender = $reader->readInt(16);
        $this->sTypoDescender = $reader->readInt(16);
        $this->sTypoLineGap = $reader->readInt(16);
        $reader->ignore(14);
        $this->sCapHeight = $reader->readInt(16);
    }
}
