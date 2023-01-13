<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

class T_hhea implements TableInterface
{
    public $ascender;
    public $descender;
    public $lineGap;
    public $numberOfHMetrics;

    private $reader;

    public function __construct(TypeReader $reader)
    {
        $reader->ignore(4); // ignore majorVersion, minorVersion
        $this->ascender = $reader->readInt(16);
        $this->descender = $reader->readInt(16);
        $this->lineGap = $reader->readInt(16);
        $reader->ignore(24);
        $this->numberOfHMetrics = $reader->readUint(16);
        $this->reader = $reader;
    }

    public function subset(int $numberOfHMetrics)
    {
        $this->reader->seek(0);
        return $this->reader->readString(34) . pack('n', $numberOfHMetrics);
    }
}
