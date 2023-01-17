<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_head
{

    public $unitsPerEm;
    public $xMin;
    public $yMin;
    public $xMax;
    public $yMax;
    public $indexToLocFormat;

    private $reader;

    public function __construct(TypeReader $reader)
    {
        $reader->ignore(18);
        $this->unitsPerEm = $reader->readUint(16);
        $reader->ignore(16);
        $this->xMin = $reader->readInt(16);
        $this->yMin = $reader->readInt(16);
        $this->xMax = $reader->readInt(16);
        $this->yMax = $reader->readInt(16);
        $reader->ignore(6);
        $this->indexToLocFormat = $reader->readInt(16);
        $this->reader = $reader;
    }

    public function subset()
    {
        $reader = $this->reader;
        return $reader->slice(0, 50) . "\x00\x01" . $reader->slice(52, 54);
    }
}
