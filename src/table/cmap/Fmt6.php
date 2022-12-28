<?php
namespace ren1244\sfnt\table\cmap;

use ren1244\sfnt\TypeReader;

class Fmt6 implements FormatInterface
{
    public static function getCodeToGid(TypeReader $reader, array &$result)
    {
        $reader->ignore(6);
        $firstCode = $reader->readUint(16);
        $entryCount = $reader->readUint(16);
        for($i=0;$i<$entryCount;++$i) {
            $cid = $firstCode + $i;
            $gid = $reader->readUint(16);
            $result[$cid] = $gid;
        }
        return $result;
    }
}
