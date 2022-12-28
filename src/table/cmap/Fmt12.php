<?php

namespace ren1244\sfnt\table\cmap;

use ren1244\sfnt\TypeReader;

class Fmt12 implements FormatInterface
{
    public static function getCodeToGid(TypeReader $reader, array &$result)
    {
        $reader->ignore(12); // ignore format, reserved, length, language
        $numGroups = $reader->readUint(32);
        for ($i = 0; $i < $numGroups; ++$i) {
            $startCharCode = $reader->readUint(32);
            $endCharCode = $reader->readUint(32);
            $startGlyphID = $reader->readUint(32);
            for ($c = $startCharCode; $c <= $endCharCode; ++$c) {
                $result[$c] = $startGlyphID + ($c - $startCharCode);
            }
        }
        return $result;
    }
}
