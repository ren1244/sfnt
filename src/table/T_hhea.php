<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_hhea implements TableInterface
{
    public function __construct(TypeReader $reader)
    {
        $reader->ignore(4); // ignore majorVersion, minorVersion
        $this->ascender = $reader->readInt(16);
        $this->descender = $reader->readInt(16);
        $this->lineGap = $reader->readInt(16);
        $reader->ignore(24);
        $this->numberOfHMetrics = $reader->readUint(16);
    }
}
