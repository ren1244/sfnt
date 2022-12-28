<?php

namespace ren1244\sfnt\cff;

use Exception;
use ren1244\sfnt\TypeReader;
use TypeError;

class INDEX implements \ArrayAccess, \Countable
{

    /** @var string 內容類別 */
    private $itemClassname;

    /** @var array 內容實體陣列 */
    private $container = [];

    /** @var int 元素個數 */
    private $count = 0;

    /**
     * 以下屬性作為讀取器時才存在
     */

    /** @var TypeReader|null 來源資料 */
    private $reader;

    /** @var int|null 讀取器起始位置 */
    private $startOffset;

    /** @var int|null 讀取器結束位置 */
    private $endOffset;

    /** @var array|null 偏移陣列 */
    private $offsetArray;

    /** @var int|null 偏移起始位置 */
    private $offsetBegin;


    /**
     * 建立一個 INDEX
     * 若不給第二個與第三個參數，可以搭配 push 方法用來處理寫入
     *
     * @param  string $itemClassname 內容類別
     * @param  TypeReader|null $reader 讀取器
     * @param  int|null $startOffset 讀取起始位置
     * @return void
     */
    public function __construct(string $itemClassname, TypeReader $reader = null, int $startOffset = null)
    {
        $this->itemClassname = $itemClassname;
        if ($reader) {
            $this->readFromTypeReader($reader, $startOffset);
        }
    }

    /**
     * 從讀取器讀取資料
     * 各元素資料為 lazy load
     *
     * @param  TypeReader $reader 讀取器
     * @param  int $startOffset 讀取起始位置
     * @return void
     */
    public function readFromTypeReader(TypeReader $reader, int $startOffset)
    {
        $this->reader = $reader;
        $this->startOffset = $startOffset;
        $reader->seek($startOffset);
        if (($this->count = $reader->readUint(16)) > 0) {
            $offSize = $reader->readUint(8);
            $this->offsetArray = $reader->readUintArray($offSize << 3, $this->count + 1);
            $this->offsetBegin = $reader->getIdx() - 1;
            // 把讀取位置移動到最後
            $this->endOffset = $this->offsetBegin + $this->offsetArray[$this->count];
            $reader->seek($this->endOffset);
        } else {
            $this->endOffset = $reader->getIdx();
        }
    }

    /**
     * 依據 count 計算 bias 值
     * 這應該只用在 subrsINDEX
     *
     * @return void
     */
    public function getSubrBias()
    {
        return $this->count < 1240 ? 107 : ($this->count < 33900 ? 1131 : 32768);
    }

    /**
     * 取得最後一個 offsetArray 讀取結束後的位置
     *
     * @return void
     */
    public function getEndOffset()
    {
        return $this->endOffset;
    }

    /**
     * 從讀取器複製整塊資料
     *
     * @return string
     */
    public function copy()
    {
        return $this->reader->slice($this->startOffset, $this->endOffset);
    }

    /**
     * 增加一筆資料（應在無讀取器時使用）
     *
     * @param  mixed $classData
     * @return void
     */
    public function append($classData)
    {
        if (gettype($classData) === $this->itemClassname || get_class($classData) === $this->itemClassname) {
            $this->container[] = $classData;
        } else {
            throw new TypeError("$this->itemClassname Required");
        }
    }

    /**
     * 取子集（應在無讀取器時使用）
     *
     * @return string
     */
    public function subset()
    {
        /**
         * 若資料為一般字串，直接取之
         * 若為類別，會由類別的 subset 取值
         */
        $count = count($this->container);
        if ($count === 0) {
            return "\x00\x00"; // empty INDEX
        }
        if ($this->itemClassname === 'string') {
            $arr = [$idx = 1];
            foreach ($this->container as $str) {
                $arr[] = ($idx += strlen($str));
            }
            $tmp = $this->container;
        } else {
            $arr = [$idx = 1];
            $tmp = [];
            foreach ($this->container as $obj) {
                $str = $obj->subset();
                $arr[] = ($idx += strlen($str));
                $tmp[] = $str;
            }
        }
        if ($idx < 0x100) {
            return pack('nCC*', $count, 1, ...$arr) . implode('', $tmp);
        } elseif ($idx < 0x10000) {
            return pack('nCn*', $count, 2, ...$arr) . implode('', $tmp);
        } else {
            return pack('nCN*', $count, 4, ...$arr) . implode('', $tmp);
        }
    }
    
    /**
     * 計算包裝成 INDEX 的大小
     *
     * @param  mixed $contentLength 所有元素長度總和
     * @param  mixed $count 元素個數
     * @return int
     */
    public static function getWrapperSize(int $contentLength, $count)
    {
        if ($contentLength < 0x100) {
            return 3 + ($count + 1) * 1;
        } elseif ($contentLength < 0x10000) {
            return 3 + ($count + 1) * 2;
        } else {
            return 3 + ($count + 1) * 4;
        }
    }

    /**
     * 除錯時，觀察 offsetArray 用
     *
     * @return string
     */
    public function __toString()
    {
        return 'offsets = ' . implode(', ', $this->offsetArray);
    }

    /**
     * 以下為 \ArrayAccess, \Countable 的實作
     */

    public function offsetExists($offset)
    {
        if (is_int($offset) && 0 <= $offset && $offset < $this->count) {
            return true;
        }
        return false;
    }

    public function offsetGet($offset)
    {
        if (is_int($offset) && 0 <= $offset && $offset < $this->count) {
            if (!isset($this->container[$offset])) {
                $reader = $this->reader;
                $startPos = $this->offsetBegin + $this->offsetArray[$offset];
                $endPos = $this->offsetBegin + $this->offsetArray[$offset + 1];
                $classname = $this->itemClassname;
                if ($classname === 'string') {
                    $reader->seek($startPos);
                    $this->container[$offset] = $reader->readString($endPos - $startPos);
                } else {
                    $this->container[$offset] = new $classname($reader, $startPos, $endPos);
                }
            }
            return $this->container[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception('read only');
    }

    public function offsetUnset($offset)
    {
        throw new Exception('read only');
    }

    public function count()
    {
        return $this->count;
    }
}
