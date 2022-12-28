<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_head implements TableInterface
{
    public function __construct(TypeReader $reader)
    {
        $reader->ignore(18);
        $this->unitsPerEm = $reader->readUint(16);
        $reader->ignore(16);
        $this->xMin = $reader->readInt(16);
        $this->yMin = $reader->readInt(16);
        $this->xMax = $reader->readInt(16);
        $this->yMax = $reader->readInt(16);
    }
}
