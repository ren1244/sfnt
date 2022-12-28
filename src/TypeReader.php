<?php

namespace ren1244\sfnt;

use Exception;
use LDAP\Result;

class TypeReader
{
    const CFF_REAL = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', 'E', 'E-', '', '-', ''];

    /** @var string 字型原始資料 */
    private $data = '';

    /** @var int 讀取到位置 */
    private $idx = 0;

    /** @var int data 區塊結束位置 */
    private $end = 0;

    /** @var int 區塊起始位置 */
    private $start = 0;

    /**
     * __construct
     *
     * @param  string $fontBinaryData 字型原始資料
     * @return void
     */
    public function __construct(string $fontBinaryData, $start = 0, $len = null)
    {
        $this->data = $fontBinaryData;
        $this->start = $this->idx = $start;
        $this->end = $len !== null ? $start + $len : strlen($this->data);
    }

    public function createSubReader($offset, $length = null, $absoluteFlag = false)
    {
        if ($absoluteFlag) {
            return new TypeReader($this->data, $offset, $length);
        } else {
            return new TypeReader($this->data, $this->start + $offset, $length);
        }
    }

    public function seek($pos)
    {
        $this->idx = $this->start + $pos;
    }

    public function ignore($n)
    {
        $this->idx += $n;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function getEnd()
    {
        return $this->end;
    }

    public function getIdx()
    {
        return $this->idx - $this->start;
    }

    public function getLength()
    {
        return $this->end - $this->start;
    }

    public function getData()
    {
        return $this->data;
    }

    public function slice($start, $end)
    {
        return substr($this->data, $this->start + $start, $end - $start);
    }

    /**
     * 讀取有號整數
     *
     * @param  int $bits 允許的值有: 8、16、32
     * @return int
     */
    public function readInt(int $bits)
    {
        if ($this->idx + ($bits >> 3) > $this->end) {
            throw new Exception('No more data can be read');
        }
        switch ($bits) {
            case 8:
                $x = unpack('c', $this->data, $this->idx)[1];
                break;
            case 16:
                $x = unpack('n', $this->data, $this->idx)[1];
                if ($x > 0x7fff) {
                    $x -= 0x10000;
                }
                break;
            case 32:
                $x = unpack('N', $this->data, $this->idx)[1];
                if ($x > 0x7fffffff) {
                    $x -= 0x100000000;
                }
                break;
            default:
                throw new Exception('Parameter bits must be one of 8, 16, 32');
        }
        $this->idx += ($bits >> 3);
        return $x;
    }

    /**
     * 讀取無號整數
     *
     * @param  int $bits 允許的值有: 8、16、24、32
     * @return int
     */
    public function readUint(int $bits)
    {
        if ($this->idx + ($bits >> 3) > $this->end) {
            throw new Exception('No more data can be read');
        }
        switch ($bits) {
            case 8:
                $x = unpack('C', $this->data, $this->idx)[1];
                break;
            case 16:
                $x = unpack('n', $this->data, $this->idx)[1];
                break;
            case 24:
                $x = unpack('Ch/nl', $this->data, $this->idx);
                $x = $x['h'] << 16 | $x['l'];
                break;
            case 32:
                $x = unpack('N', $this->data, $this->idx)[1];
                break;
            default:
                throw new Exception('Parameter bits must be one of 8, 16, 24, 32');
        }
        $this->idx += ($bits >> 3);
        return $x;
    }

    public function readIntArray(int $bits, int $count)
    {
        if ($count < 1) {
            return [];
        }
        if ($this->idx + ($bits >> 3) * $count > $this->end) {
            throw new Exception('No more data can be read');
        }
        switch ($bits) {
            case 8:
                $x = array_values(unpack('c' . $count, $this->data, $this->idx));
                break;
            case 16:
                $x = array_values(unpack('n' . $count, $this->data, $this->idx));
                $x = array_map(function ($v) {
                    return $v < 0x8000 ? $v : $v - 0x10000;
                }, $x);
                break;
            case 32:
                $x = array_values(unpack('N' . $count, $this->data, $this->idx));
                if ($x > 0x7fffffff) {
                    $x -= 0x100000000;
                }
                break;
            default:
                throw new Exception('Parameter bits must be one of 8, 16, 32');
        }
        $this->idx += ($bits >> 3) * $count;
        return $x;
    }

    public function readUintArray(int $bits, int $count)
    {
        if ($count < 1) {
            return [];
        }
        if ($this->idx + ($bits >> 3) * $count > $this->end) {
            throw new Exception('No more data can be read');
        }
        switch ($bits) {
            case 8:
                $x = array_values(unpack('C' . $count, $this->data, $this->idx));
                break;
            case 16:
                $x = array_values(unpack('n' . $count, $this->data, $this->idx));
                break;
            case 24:
                $x = [];
                $k = $this->idx;
                for ($i = 0; $i < $count; ++$i) {
                    $x[] = ord($this->data[$k]) << 16 | ord($this->data[$k + 1]) << 8 | ord($this->data[$k + 2]);
                    $k += 3;
                }
                break;
            case 32:
                $x = array_values(unpack('N' . $count, $this->data, $this->idx));
                break;
            default:
                throw new Exception('Parameter bits must be one of 8, 16, 24, 32');
        }
        $this->idx += ($bits >> 3) * $count;
        return $x;
    }

    /**
     * 讀取 Fixed
     *
     * @return float
     */
    public function readFixed()
    {
        $x = $this->readInt(32);
        return $x / 0x10000;
    }

    /**
     * 讀取 FWORD
     *
     * @return int
     */
    public function readFWORD()
    {
        return $this->readInt(16);
    }

    /**
     * 讀取 UFWORD
     *
     * @return int
     */
    public function readUFWORD()
    {
        return $this->readUint(16);
    }

    /**
     * 讀取 F2DOT14
     *
     * @return float
     */
    public function readF2DOT14()
    {
        $x = $this->readUint(16);
        $n = $x >> 14 & 3;
        $f = ($x & 0x3fff) / 16384;
        return ($n > 1 ? $n - 4 : $n) + $f;
    }

    /**
     * 讀取 LONGDATETIME
     *
     * @return int
     */
    public function readLONGDATETIME()
    {
        if ($this->idx + 8 > $this->end) {
            throw new Exception('No more data can be read');
        }
        $x = unpack('N2', $this->data, $this->idx);
        $this->idx += 8;
        return $x[1] << 32 | $x[2];
    }

    /**
     * 讀取 Tag
     *
     * @return string
     */
    public function readTag()
    {
        if ($this->idx + 4 > $this->end) {
            throw new Exception('No more data can be read');
        }
        $x = substr($this->data, $this->idx, 4);
        $this->idx += 4;
        return $x;
    }

    public function readOffset($bits)
    {
        return $this->readUint($bits);
    }

    public function readVersion16Dot16()
    {
    }

    public function readSID()
    {
        if ($this->idx + 2 > $this->end) {
            throw new Exception('No more data can be read');
        }
        $x = substr($this->data, $this->idx, 2);
        $this->idx += 2;
        return $x;
    }

    public function readString($nChar)
    {
        if ($this->idx + $nChar > $this->end) {
            throw new Exception('No more data can be read');
        }
        $x = substr($this->data, $this->idx, $nChar);
        $this->idx += $nChar;
        return $x;
    }

    /**
     * 讀取一個 CFF 的 Dict
     * 第三個參數參考 ren1244\sfnt\cff\PrivateDict 內的說明
     *
     * 回傳值: array(2)
     * [0] => dict array
     * [1] => slice array
     *     格式為: [start0, end0, operator0, start1, end1, operator1, ...]
     *     若最後一段為 operator，有 3n 個元素
     *     若最後一段無 operator，有 3n - 1 個元素
     *     注意: 連續遇到 operator 時，start 可能跟 end 位置相同
     * 
     * @param  mixed $relStart 起始位置
     * @param  mixed $relEnd 結束位置
     * @param  mixed $operatorMap 定義 operators
     * @return array
     */
    public function readDict(int $relStart, int $relEnd, array $operatorMap)
    {
        $this->idx = $this->start + $relStart;
        $endPos = $this->start + $relEnd;
        $result = []; // for dict array
        $operands = [];
        $sliceArray = []; // 分段
        $sliceStartIdx = $this->idx; // 紀錄分段起始點
        $sliceStageIdx = $this->idx; // 紀錄分段暫存點
        while ($this->idx < $endPos) {
            $b0 = ord($ch = $this->data[$this->idx++]);
            if ($b0 < 22) {
                //operators
                if ($b0 === 12) {
                    $ch .= $this->data[$this->idx++];
                }
                if (isset($operatorMap[$ch])) {
                    $opCfg = $operatorMap[$ch];
                    if ($opCfg[1]) { // 存成陣列或數值
                        $result[$opCfg[0]] = $operands;
                    } elseif (count($operands) === 1) {
                        $result[$opCfg[0]] = $operands[0];
                    } else {
                        throw new Exception('bad operands. count = ' . count($operands) . ' idx = ' . ($this->idx - $this->start) . ' s = ' . bin2hex(substr($this->data, $this->start, $this->end - $this->start)));
                    }
                    if ($opCfg[3]) { // 是否為可變的 operator，若是，分段
                        $sliceArray[] = $sliceStartIdx - $this->start;
                        $sliceArray[] = $sliceStageIdx - $this->start;
                        $sliceArray[] = $ch;
                        $sliceStartIdx = $sliceStageIdx = $this->idx;
                    } else {
                        $sliceStageIdx = $this->idx;
                    }
                    $operands = [];
                    continue;
                }
                throw new Exception('bad operator ' . bin2hex($ch));
            } elseif ($b0 < 32) {
                if ($b0 === 28) {
                    $x = unpack('n', $this->data, $this->idx)[1];
                    $this->idx += 2;
                    $operands[] = $x & 0x8000 ? $x - 0x10000 : $x;
                    continue;
                } elseif ($b0 === 29) {
                    $x = unpack('N', $this->data, $this->idx)[1];
                    $this->idx += 4;
                    $operands[] = $x & 0x80000000 ? $x - 0x100000000 : $x;
                    continue;
                } elseif ($b0 === 30) {
                    $s = '';
                    while (1) {
                        $x = unpack('C', $this->data, $this->idx)[1];
                        ++$this->idx;
                        if (($v = $x >> 4 & 15) === 0xf) {
                            break;
                        }
                        $s .= self::CFF_REAL[$v];
                        if (($v = $x & 15) === 0xf) {
                            break;
                        }
                        $s .= self::CFF_REAL[$v];
                    }
                    $operands[] = floatval($s);
                    continue;
                }
            } elseif ($b0 < 247) {
                $operands[] = $b0 - 139;
                continue;
            } elseif ($b0 < 255) {
                $b1 = unpack('C', $this->data, $this->idx)[1];
                ++$this->idx;
                $operands[] = $b0 < 251 ? ($b0 - 247) * 256 + $b1 + 108 : - ($b0 - 251) * 256 - $b1 - 108;
                continue;
            }
            throw new Exception('bad encoding');
        }
        // 分段最後一筆資料
        if ($sliceStartIdx !== $sliceStageIdx) {
            $sliceArray[] = $sliceStartIdx - $this->start;
            $sliceArray[] = $sliceStageIdx - $this->start;
        }
        // 寫入預設值
        foreach ($operatorMap as $opCfg) {
            if ($opCfg[2] !== null && !isset($result[$opCfg[0]])) {
                $result[$opCfg[0]] = $opCfg[2];
            }
        }
        return [$result, $sliceArray];
    }

    public function __toString()
    {
        return "[strt = $this->start, end = $this->end, idx = $this->idx" . ($this->idx - $this->start) . ", len = " . ($this->end - $this->start) . "]";
    }
}
