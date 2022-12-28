<?php
namespace ren1244\sfnt\table\cmap;

use ren1244\sfnt\TypeReader;

interface FormatInterface
{
    public static function getCodeToGid(TypeReader $reader, array &$result);
}
