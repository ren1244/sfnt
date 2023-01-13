<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_maxp implements TableInterface
{
    public $numGlyphs;

    private $reader;

    public function __construct(TypeReader $reader)
    {
        $version = $reader->readUint(32);
        $this->numGlyphs = $reader->readUint(16);
        if ($version === 0x00005000) {
            return;
        } elseif ($version === 0x00010000) {
        } else {
            throw new Exception('unknow maxp table version');
        }
        $this->reader = $reader;
    }

    public function subset(int $numGlyphs)
    {
        return $this->reader->slice(0, 4) .
            pack('n', $numGlyphs) .
            $this->reader->slice(6, $this->reader->getLength());
    }
}
