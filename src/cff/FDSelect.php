<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\TypeReader;

class FDSelect
{
    public function __construct(TypeReader $reader, int $start, int $nGlyphs)
    {
        $reader->seek($start);
        $this->nGlyphs = $nGlyphs;
        $this->format = $reader->readUint(8);
        switch ($this->format) {
            case 0:
                $this->fds = $reader->readUintArray(8, $nGlyphs);
                $this->searchFunc = 'searchFuncFmt1';
                break;
            case 3:
                $nRanges = $reader->readUint(16);
                $this->fds = $reader->readUintArray(24, $nRanges);
                $xx = $reader->readUint(16);
                $this->fds[] = ($xx << 8);
                $this->searchFunc = 'searchFuncFmt3';
                break;
            default:
                throw new Exception('Unknow format ' . $this->format . ' of FDSelect');
        }
    }

    public function __toString()
    {
        if ($this->format === 1) {
            return "format = 1\n" . implode(', ', $this->fds) . "\n";
        } else {
            $n = count($this->fds);
            $s = [];
            for ($i = 0; $i < $n; ++$i) {
                $first = $this->fds[$i] >> 8;
                $fd = $this->fds[$i] & 0xff;
                $s[] = "($i, $first, $fd)";
            }
            $s = implode(' ', $s);
            return "format = 3\n$s\n\n";
        }
    }

    public function getFDIndex(int $GID)
    {
        return $this->{$this->searchFunc}($GID);
    }

    private function searchFuncFmt1(int $GID)
    {
        return isset($this->fds[$GID]) ? $this->fds[$GID] : null;
    }

    private function searchFuncFmt3(int $GID)
    {
        $start = 0;
        $end = count($this->fds) - 1;
        if ($GID < ($this->fds[$start] >> 8) || ($this->fds[$end] >> 8) <= $GID) {
            return null;
        }
        // 用二分搜尋法找出 index, where (fd[index]>>8) <= gid < (fd[index+1]>>8)
        while ($start < $end) {
            $mid = $start + $end + 1 >> 1;
            $x = $this->fds[$mid] >> 8;
            if ($x < $GID) {
                $start = $mid;
            } elseif ($GID < $x) {
                $end = $mid - 1;
            } else {
                return $this->fds[$mid] & 0xff;
            }
        }
        return $this->fds[$start] & 0xff;
    }

    // private function searchFuncFmt3B(int $GID)
    // {
    //     $end = count($this->fds) - 1;
    //     if ($GID < 0 || ($this->fds[$end] >> 8) <= $GID) {
    //         return null;
    //     }
    //     while ($end >= 0 && ($this->fds[$end] >> 8) > $GID) {
    //         --$end;
    //     }
    //     return $end >= 0 ? $this->fds[$end] & 0xff : null;
    // }

    // public function test()
    // {
    //     $end = $this->nGlyphs + 1;
    //     for ($i = -1; $i < $end; ++$i) {
    //         $x = $this->searchFuncFmt3($i);
    //         $y = $this->searchFuncFmt3B($i);
    //         if ($x !== $y) {
    //             throw new Exception("[$i] $x !== $y");
    //         }
    //         echo "[$i $x $y]";
    //     }
    // }
}
