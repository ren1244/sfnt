<?php

namespace ren1244\sfnt\table;

use ren1244\sfnt\TypeReader;

interface TableInterface
{
    public function __construct(TypeReader $reader);
}
