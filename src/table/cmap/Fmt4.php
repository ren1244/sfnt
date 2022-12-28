<?php

namespace ren1244\sfnt\table\cmap;

use ren1244\sfnt\TypeReader;

class Fmt4 implements FormatInterface
{
    public static function getCodeToGid(TypeReader $reader, array &$result)
    {
        $reader->ignore(6); // ignore format, length, language
        $segCount = $reader->readUint(16) >> 1;
        $reader->ignore(6); // ignore searchRange, entrySelector, rangeShift
        $endCode = $reader->readUintArray(16, $segCount);
        $reader->ignore(2); // ignore reservedPad
        $startCode = $reader->readUintArray(16, $segCount);
        $idDelta =  $reader->readIntArray(16, $segCount);
        $idAddrStart = $reader->getIdx();
        $idRangeOffsets = $reader->readUintArray(16, $segCount);
        for ($i = 0; $i < $segCount; ++$i) {
            $s = $startCode[$i];
            $e = $endCode[$i];
            $d = $idDelta[$i];
            $o = $idRangeOffsets[$i];
            if ($o === 0) {
                for ($cid = $s; $cid <= $e; ++$cid) {
                    $gid = $cid + $d;
                    $result[$cid] = $gid & 0xffff;
                }
            } else {
                $reader->seek($idAddrStart + $i * 2 + $o);
                for ($cid = $s; $cid <= $e; ++$cid) {
                    $gid = $reader->readUint(16);
                    $result[$cid] = $gid;
                }
            }
        }
        return $result;
    }
}
