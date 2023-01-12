<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\TypeReader;

class PrivateDict implements \ArrayAccess
{
    const OPERATORS = [
        /**
         * key: operator
         * value[0]: 欄位名稱
         * value[1]: 若是為 true 則取出為陣列，否則取出為單一數值
         * value[2]: 預設值
         * value[3]: 若設為 true 代表這是之後寫入可能會變動的數值
         */
        "\x06" => ['BlueValues', true, null, false],
        "\x07" => ['OtherBlues', true, null, false],
        "\x08" => ['FamilyBlues', true, null, false],
        "\x09" => ['FamilyOtherBlues', true, null, false],
        "\x0c\x09" => ['BlueScale', false, 0.039625, false],
        "\x0c\x0a" => ['BlueShift', false, 7, false],
        "\x0c\x0b" => ['BlueFuzz', false, 1, false],
        "\x0a" => ['StdHW', false, null, false],
        "\x0b" => ['StdVW', false, null, false],
        "\x0c\x0c" => ['StemSnapH', true, null, false],
        "\x0c\x0d" => ['StemSnapV', true, null, false],
        "\x0c\x0e" => ['ForceBold', false, 0, false],
        "\x0c\x11" => ['LanguageGroup', false, 0, false],
        "\x0c\x12" => ['ExpansionFactor', false, 0.06, false],
        "\x0c\x13" => ['initialRandomSeed', false, 0, false],
        "\x13" => ['Subrs', false, null, true],
        "\x14" => ['defaultWidthX', false, 0, false],
        "\x15" => ['nominalWidthX', false, 0, false],
    ];

    use DictTrait;

    private $reader;
    private $start;
    private $end;
    private $cacheSubr = null;
    private $usedSubrs = [];
    private $sliceArray;

    public function __construct(TypeReader $reader, int $start, int $end)
    {
        $this->reader = $reader;
        $this->start = $start;
        $this->end = $end;
        list($this->container, $this->sliceArray) = $reader->readDict($start, $end, self::OPERATORS);
    }

    /**
     * 取得 Subrs
     *
     * @return INDEX|null INDEX of string
     */
    public function getSubrs()
    {
        if (!$this->cacheSubr && isset($this->container['Subrs'])) {
            $this->cacheSubr = new INDEX('string', $this->reader, $this->start + $this->container['Subrs']);
        }
        return $this->cacheSubr;
    }

    public function setUsed(int $idx)
    {
        $this->usedSubrs[$idx] = true;
    }

    public function getUsed()
    {
        return $this->usedSubrs;
    }

    /**
     * 依據 usedSubrs 產生 Private Dict + Subrs
     *
     * @return array [0] => Private Dict Content + Subrs Content
     *               [1] => Private Dict Lnegth
     */
    public function subset()
    {
        // 依據 usedSubrs 產生新的 subrs
        $newSubrs = '';
        if (($oldSubrs = $this->getSubrs()) !== null) {
            $newSubrs = new INDEX('string');
            $n = count($oldSubrs);
            for ($i = 0; $i < $n; ++$i) {
                if (isset($this->usedSubrs[$i])) {
                    $newSubrs->append($oldSubrs[$i]);
                } else {
                    $newSubrs->append("\x0b");
                }
            }
            $newSubrs = $newSubrs->subset();
        }
        // 產生結果
        $n = count($this->sliceArray);
        $result = [];
        $len = 0;
        $idx = null; // subrs 在 result 的哪個 index
        for ($i = 0; $i + 2 < $n; $i += 3) {
            $start = $this->sliceArray[$i];
            $end = $this->sliceArray[$i + 1];
            $op = $this->sliceArray[$i + 2];
            if ($op !== "\x13") { //
                throw new Exception('unknow operator');
            }
            $result[] = $this->reader->slice($start, $end);
            $idx = count($result);
            $result[] = ''; // 預留給 subrs
            $len += $end - $start + 6; // 6 是 subrs 的長度
        }
        if ($i + 1 < $n) {
            $start = $this->sliceArray[$i];
            $end = $this->sliceArray[$i + 1];
            $result[] = $this->reader->slice($start, $end);
            $len += $end - $start;
        }
        if ($idx !== null) {
            $result[$idx] = pack('CNC', 29, $len, 0x13); // 寫入 subrs 位置
        }
        $result[] = $newSubrs;
        return [implode('', $result), $len];
    }
}
