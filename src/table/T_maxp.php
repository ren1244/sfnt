<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_maxp implements TableInterface
{
    public function __construct(TypeReader $reader)
    {
        $version = $reader->readUint(32);
        $this->numGlyphs = $reader->readUint(16);
        if($version === 0x00005000) {
            return;
        } elseif($version === 0x00010000) {
            
        } else {
            throw new Exception('unknow maxp table version');
        }
    }
}
