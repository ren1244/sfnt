<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\cff\Charset;
use ren1244\sfnt\cff\CharstringParser;
use ren1244\sfnt\cff\CharstringRecoder;
use ren1244\sfnt\cff\FDSelect;
use ren1244\sfnt\cff\FontDict;
use ren1244\sfnt\cff\PrivateDict;
use ren1244\sfnt\TypeReader;
use ren1244\sfnt\cff\INDEX;

class T_CFF_ implements TableInterface
{
    /** @var TypeReader 讀取器 */
    private $reader;

    /** @var array 使用到的 GID */
    private $usedGID = [0 => true];

    /** @var INDEX 字型名稱集合 */
    public $nameINDEX;

    /** @var string 字型名稱 */
    public $fontname;

    /** @var FontDict 字型資訊 */
    public $topDict;

    /** @var INDEX 字串集合 */
    public $stringINDEX;

    /** @var INDEX gSubrs */
    public $gSubrINDEX;

    public $subrs = [];

    public function __construct(TypeReader $reader)
    {
        $this->reader = $reader;

        // Header
        $reader->ignore(2);
        $hdrSize = $reader->readUint(8);
        $reader->ignore(1);

        // Name INDEX: 字型名稱[]
        $this->nameINDEX = new INDEX('string', $reader, $hdrSize);
        $this->fontname = $this->nameINDEX[0];

        // Top DICT INDEX: 字型資訊[]
        $topDictINDEX = new INDEX(FontDict::class, $reader, $reader->getIdx());
        $this->topDict = $topDictINDEX[0];

        // String INDEX: 字串[]
        $this->stringINDEX = new INDEX('string', $reader, $reader->getIdx());

        // Global subrs
        $this->gSubrINDEX = new INDEX('string', $reader, $reader->getIdx());
    }

    public function isCIDKeyed()
    {
        return isset($this->topDict['ROS']);
    }

    public function setUsed(int $GID)
    {
        $this->usedGID[$GID] = true;
    }

    /**
     * 取得字型子集
     *
     * @return string
     */
    public function subset()
    {
        if (!$this->isCIDKeyed()) {
            throw new Exception('尚未完成對非 CIDKeyed CFF 取子集');
        }

        $reader = $this->reader;
        $topDict = $this->topDict;
        $gSubrs = $this->gSubrINDEX;
        $charstrings = $topDict->getCharstringINDEX();

        // 解析並記錄使用到哪些 gSubrs 與 subrs
        $recoder = new CharstringRecoder();
        foreach ($this->usedGID as $GID => $x) {
            $priv = $this->topDict->getPrivate($GID);
            $recoder->privateDict = $priv;
            CharstringParser::parse($charstrings[$GID], $gSubrs, $priv->getSubrs(), $recoder);
        }

        /**
         * $result:
         * [0] Header + Name INDEX
         * [1] Top DICT INDEX –
         * [2] String INDEX –
         * [3] Global Subr INDEX –
         * [4+] 由 Top DICT 產生
         */
        $result = [];

        // Header + nameINDEX
        $result[] = $reader->slice(0, $this->nameINDEX->getEndOffset());

        // Top Dict
        $result[] = ''; // 先保留

        // String INDEX
        $result[] = $this->stringINDEX->copy();

        // Global Subr INDEX
        $result[] = $this->getSubsetGSubrINDEX($recoder->usedGsubrs);

        // 依據 Font Dict 結構建立其他內容
        $newINDEX = new INDEX('string');
        $newINDEX->append($topDict->subset($this->usedGID, $result));
        $result[1] = $newINDEX->subset();

        return implode($result);
    }

    private function getSubsetGSubrINDEX(array $usedGSubrs)
    {
        /**
         * 一樣維持 gSubrINDEX 的原數量
         * 只是沒使用到的部分內容為空字串
         */
        $result = new INDEX('string');
        $n = count($this->gSubrINDEX);
        for ($i = 0; $i < $n; ++$i) {
            if (isset($usedGSubrs[$i])) {
                $result->append($this->gSubrINDEX[$i]);
            } else {
                $result->append("\x0b");
            }
        }
        return $result->subset();
    }

    public function getBlock()
    {
        $reader = $this->reader;
        $startPos = $reader->getStart();
        $endPos = $reader->getEnd();
        return substr($reader->getData(), $startPos, $endPos - $startPos);
    }
}
