<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\TypeReader;

class FontDict implements \ArrayAccess
{
    const OPERATORS = [
        "\x00" => ['version', false, null, false],
        "\x01" => ['Notice', false, null, false],
        "\x0c\x00" => ['Copyright', false, null, false],
        "\x02" => ['FullName', false, null, false],
        "\x03" => ['FamilyName', false, null, false],
        "\x04" => ['Weight', false, null, false],
        "\x0c\x01" => ['isFixedPitch', false, 0, false],
        "\x0c\x02" => ['ItalicAngle', false, 0, false],
        "\x0c\x03" => ['UnderlinePosition', false, -100, false],
        "\x0c\x04" => ['UnderlineThickness', false, 50, false],
        "\x0c\x05" => ['PaintType', false, 0, false],
        "\x0c\x06" => ['CharstringType', false, 2, false],
        "\x0c\x07" => ['FontMatrix', true, [0.001, 0, 0, 0.001, 0, 0], false],
        "\x0d" => ['UniqueID', false, null, false],
        "\x05" => ['FontBBox', true, [0, 0, 0, 0], false],
        "\x0c\x08" => ['StrokeWidth', false, 0, false],
        "\x0e" => ['XUID', true, null, false],
        "\x0f" => ['charset', false, 0, true], // [Top]subset 變更: 改成 GID = CID
        "\x10" => ['Encoding', false, 0, true], // CIDKeyed 沒有
        "\x11" => ['CharStrings', false, null, true], // [Top]subset 變更
        "\x12" => ['Private', true, null, true], //[FD]subset 變更
        "\x0c\x14" => ['SyntheticBase', false, null, false],
        "\x0c\x15" => ['PostScript', false, null, false],
        "\x0c\x16" => ['BaseFontName', false, null, false],
        "\x0c\x17" => ['BaseFontBlend', true, null, false],
        "\x0c\x1e" => ['ROS', true, null, false],
        "\x0c\x1f" => ['CIDFontVersion', false, 0, false],
        "\x0c\x20" => ['CIDFontRevision', false, 0, false],
        "\x0c\x21" => ['CIDFontType', false, 0, false],
        "\x0c\x22" => ['CIDCount', false, 8720, true], // [Top]subset 變更
        "\x0c\x23" => ['UIDBase', false, null, false],
        "\x0c\x24" => ['FDArray', false, null, true], // [Top]subset 變更
        "\x0c\x25" => ['FDSelect', false, null, true], // [Top]subset 變更
        "\x0c\x26" => ['FontName', false, null, false],
    ];

    use DictTrait;

    private $reader;
    private $start;
    private $end;
    private $cache = [];
    private $sliceArray;

    public function __construct(TypeReader $reader, int $start, int $end)
    {
        $this->reader = $reader;
        $this->start = $start;
        $this->end = $end;
        list($this->container, $this->sliceArray) = $reader->readDict($start, $end, self::OPERATORS);
    }

    /**
     * 取得 charset
     *
     * @return Charset|null
     */
    public function getCharset()
    {
        if (!isset($this->container['charset'])) {
            return null;
        }
        if (!isset($this->cache['charset'])) {
            $this->cache['charset'] = new Charset(
                $this->reader,
                $this->container['charset'],
                count($this->getCharstringINDEX())
            );
        }
        return $this->cache['charset'];
    }

    /**
     * 取得 charstring INDEX
     *
     * @return INDEX|null
     */
    public function getCharstringINDEX()
    {
        if (!isset($this->container['CharStrings'])) {
            return null;
        }
        if (!isset($this->cache['CharStrings'])) {
            $this->cache['CharStrings'] = new INDEX(
                'string',
                $this->reader,
                $this->container['CharStrings']
            );
        }
        return $this->cache['CharStrings'];
    }

    /**
     * 取得 Private
     * 若有指定 GID，則依據 GID 回傳自己或是下層的 Private
     * 若不指定 GID，則只回傳自己這層的 Private
     *
     * @param  int|null $GID
     * @return Private|null
     */
    public function getPrivate(int $GID = null)
    {
        // 當指定 GID 且有 FDSelect 時，優先找 FDArray 內的
        if (
            $GID !== null &&
            ($FDSelect = $this->getFDSelect()) !== null &&
            ($FDArray = $this->getFDArray()) !== null &&
            ($FDIdx = $FDSelect->getFDIndex($GID)) !== null &&
            isset($FDArray[$FDIdx]) &&
            ($result = $FDArray[$FDIdx]->getPrivate($GID)) !== null
        ) {
            return $result;
        }
        // 否則回傳自己的
        if (isset($this->container['Private'])) {
            if (!isset($this->cache['Private'])) {
                $posInfo = $this->container['Private'];
                $this->cache['Private'] = new PrivateDict($this->reader, $posInfo[1], $posInfo[1] + $posInfo[0]);
            }
            return $this->cache['Private'];
        }
        return null;
    }

    /**
     * 取得 FDSelect
     *
     * @return FDSelect|null
     */
    public function getFDSelect()
    {
        if (!isset($this->container['FDSelect'])) {
            return null;
        }
        if (($charstrings = $this->getCharstringINDEX()) === null) {
            throw new Exception('不應該有 FDSelect 卻無 Charstrings');
        }
        $nGlyphs = count($charstrings);
        if (!isset($this->cache['FDSelect'])) {
            $offset = $this->container['FDSelect'];
            $this->cache['FDSelect'] = new FDSelect($this->reader, $offset, $nGlyphs);
        }
        return $this->cache['FDSelect'];
    }

    /**
     * 取得 FDArray
     *
     * @return INDEX|null
     */
    public function getFDArray()
    {
        if (!isset($this->container['FDArray'])) {
            return null;
        }
        if (!isset($this->cache['FDArray'])) {
            $this->cache['FDArray'] = new INDEX(
                self::class,
                $this->reader,
                $this->container['FDArray']
            );
        }
        return $this->cache['FDArray'];
    }

    const SUBSET_OP = [
        "\x0f" => 6,
        "\x10" => 6,
        "\x11" => 6,
        "\x12" => 11,
        "\x0c\x22" => 7,
        "\x0c\x24" => 7,
        "\x0c\x25" => 7,
    ];

    public function subset(array $usedGID, array &$result, int &$offset = null)
    {
        /**
         * 可能要處理的內容
         * charset     x0f offset(0)
         * Encoding    x10 offset(0)
         * CharStrings x11 offset(0)
         * Private     x12 [size, offset(0)]
         * CIDCount    x0cx22 number
         * FDArray     x0cx24 offset(0)
         * FDSelect    x0cx25 offset(0)
         */
        $reader = $this->reader;
        $sliceArray = $this->sliceArray;
        $n = count($this->sliceArray);
        // Top Dict 要先初始化 offset
        if ($offset === null) {
            // 計算 Top Dict 長度
            $offset = 0;
            for ($i = 0; $i < $n; $i += 3) {
                $offset += $sliceArray[$i + 1] - $sliceArray[$i];
                if ($i + 2 >= $n) {
                    break;
                }
                $op = $sliceArray[$i + 2];
                if (!isset(self::SUBSET_OP[$op])) {
                    throw new Exception('Unknow Font Dict Operator ' . bin2hex($op));
                }
                $offset += self::SUBSET_OP[$op];
            }
            $offset += INDEX::getWrapperSize($offset, 1);
            $offset += strlen($result[0]) + strlen($result[2]) + strlen($result[3]);
        }

        $output = [];
        for ($i = 0; $i < $n; $i += 3) {
            $output[] = $reader->slice($sliceArray[$i], $sliceArray[$i + 1]);
            if ($i + 2 >= $n) {
                break;
            }
            $op = $sliceArray[$i + 2];
            switch ($op) {
                case "\x0f":
                    $block = $this->getSubsetCharset($usedGID);
                    $output[] = pack('CN', 29, $offset) . $op;
                    $result[] = $block;
                    $offset += strlen($block);
                    break;
                case "\x10":
                    throw new Exception('CID Keyed Font 不應該有 Encoding');
                case "\x11":
                    $block = $this->getSubsetCharstringINDEX($usedGID);
                    $output[] = pack('CN', 29, $offset) . $op;
                    $result[] = $block;
                    $offset += strlen($block);
                    break;
                case "\x12":
                    $privSubsetInfo = $this->getPrivate()->subset();
                    $block = $privSubsetInfo[0];
                    $output[] = pack('CNCN', 29, $privSubsetInfo[1], 29, $offset) . $op;
                    $result[] = $block;
                    $offset += strlen($block);
                    break;
                case "\x0c\x22":
                    // 這邊不確定要不要算 GID = 0 的
                    $output[] = pack('CN', 29, count($usedGID)) . $op;
                    break;
                case "\x0c\x24":
                    $newINDEX = new INDEX('string');
                    $FDArray = $this->getFDArray();
                    $nFDArr = count($FDArray);
                    for ($k = 0; $k < $nFDArr; ++$k) {
                        // 這裡可能會在 $result 內增加 Private Dict 資料，並更新 $offset
                        $newFD = $FDArray[$k]->subset($usedGID, $result, $offset);
                        $newINDEX->append($newFD);
                    }
                    $block = $newINDEX->subset();
                    $output[] = pack('CN', 29, $offset) . $op;
                    $result[] = $block;
                    $offset += strlen($block);
                    break;
                case "\x0c\x25":
                    $block = $this->getSubsetFDSelect($usedGID);
                    $output[] = pack('CN', 29, $offset) . $op;
                    $result[] = $block;
                    $offset += strlen($block);
                    break;
                default:
                    throw new Exception('Unknow Font Dict Operator ' . bin2hex($op));
            }
        }
        return implode('', $output);
    }

    private function getSubsetCharset(array $usedGID)
    {
        $nGlyphs = count($usedGID);
        if ($nGlyphs < 2) {
            throw new Exception('nGlyphs must grater than 2');
        }
        return pack('Cnn', 2, 1, $nGlyphs - 2);
    }

    private function getSubsetCharstringINDEX(array $usedGID)
    {
        $result = new INDEX('string');
        $oldCharstringINDEX = $this->getCharstringINDEX();
        foreach ($usedGID as $gid => $x) {
            $result->append($oldCharstringINDEX[$gid]);
        }
        return $result->subset();
    }

    private function getSubsetFDSelect(array $usedGID)
    {
        $oldFDSelect = $this->getFDSelect();
        $result = "\x00";
        foreach ($usedGID as $gid => $x) {
            $fdIdx = $oldFDSelect->getFDIndex($gid);
            $result .= chr($fdIdx);
        }
        return $result;
    }
}
