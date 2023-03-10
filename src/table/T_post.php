<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_post
{
    public $italicAngle;

    public function __construct(TypeReader $reader)
    {
        $reader->ignore(4);
        $this->italicAngle = $reader->readFixed();
    }
}
