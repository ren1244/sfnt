<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_glyf implements TableInterface
{
    private $reader;

    public function __construct(TypeReader $reader)
    {
        $this->reader = $reader;
    }
    
    /**
     * 取得 subset 的 glyf 資料
     * 同時也會幫 $loca 做 subset 的準備
     * 如果遇到複合字型，會新增相依的 glyf 到 usedGID 末端
     *
     * @param  array $usedGID 使用到的 GID（參考 Sfnt 說明）
     * @param  mixed $loca
     * @return string
     */
    public function subset(array &$usedGID, T_loca $loca)
    {
        $reader = $this->reader;
        $loca->prepareSubset();
        $loca->push(0); // for GID = 0
        $result = [];
        $queue = [];
        $queueCount = 0;
        $resultIdx = 0;
        $gidMap = []; // 映射原始 GID 到新的 GID
        $start = $end = 0;
        foreach ($usedGID as $GID => $x) {
            $loca->getGlyfPosition($GID, $start, $end);
            $reader->seek($start);
            $numberOfContours = $reader->readInt(16);
            if ($numberOfContours < 0) {
                $queue[$queueCount++] = $resultIdx; // 複合字型，放入待處理列表
            }
            $result[$resultIdx++] = $reader->slice($start, $end);
            $gidMap[$GID] = $resultIdx;
            $loca->push($end - $start);
        }
        for ($i = 0; $i < $queueCount; ++$i) {
            // 目前要處理的 Composite Glyph Description
            $desc = $result[$queue[$i]];
            $newDesc = [substr($desc, 0, 10)];
            $pos = 10;
            do {
                $flag = ord($desc[$pos]) << 8 | ord($desc[$pos + 1]);
                $GID = ord($desc[$pos + 2]) << 8 | ord($desc[$pos + 3]); // 相依的 GID
                $len = ($flag & 1 ? 8 : 6) + ($flag & 0x08 ? 2 : ($flag & 0x40 ? 4 : ($flag & 0x80 ? 8 : 0)));
                if (!isset($gidMap[$GID])) {
                    // 如果所需尚未存在則加入
                    $usedGID[$GID] = 1;
                    $loca->getGlyfPosition($GID, $start, $end);
                    $reader->seek($start);
                    $numberOfContours = $reader->readInt(16);
                    if ($numberOfContours < 0) {
                        $queue[$queueCount++] = $resultIdx; // 複合字型，放入待處理列表
                    }
                    $result[$resultIdx++] = $reader->slice($start, $end);
                    $gidMap[$GID] = $resultIdx;
                    $loca->push($end - $start);
                }
                $newDesc[] = pack('n2', $flag, $gidMap[$GID]) . substr($desc, $pos + 4, $len - 4);
                $pos += $len;
            } while ($flag & 0x20); // MORE_COMPONENTS
            $newDesc[] = substr($desc, $pos);
            $result[$queue[$i]] = implode('', $newDesc);
        }
        return implode('', $result);
    }
}
