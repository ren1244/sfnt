<?php

namespace ren1244\sfnt\cache;

use Exception;

/**
 * 對連續物件陣列快取
 */
class IndexArray implements \ArrayAccess, \Countable
{

    /** @var string 內容類別 */
    private $itemClassname;

    /** @var array 內容實體陣列 */
    private $container = [];

    /** @var int 元素個數 */
    private $count = 0;

    /** @var string 要讀取的快取資料 */
    private $data = null;

    /**
     * __construct
     *
     * @param  string $itemClassname
     * @param  string $data
     * @return void
     */
    public function __construct(string $itemClassname, string $data = null)
    {
        if(
            $itemClassname!=='string' &&
            $itemClassname!=='json' &&
            $itemClassname!=='array'
        ) {
            throw new Exception($itemClassname.' must implements '.CacheableInterface::class);
        }
        $this->itemClassname = $itemClassname;
        if ($data !== null) {
            $this->count = unpack('N', $data)[1];
            $this->data = $data;
        }
    }
    
    /**
     * 打包成字串
     *
     * @return string
     */
    public function pack()
    {
        $posArr = [$this->count, 0];
        $data = '';
        $pos = 0;
        for ($i = 0; $i < $this->count; ++$i) {
            if (isset($this->container[$i])) {
                switch($this->itemClassname) {
                    case 'json':
                    case 'array':
                        $tmp = json_encode($this->container[$i]);
                        break;
                    case 'string':
                        $tmp = $this->container[$i];
                        break;
                }
                $posArr[] = ($pos += strlen($tmp));
                $data .= $tmp;
            } else {
                $posArr[] = $pos;
            }
        }
        return pack('N' . count($posArr), ...$posArr) . $data;
    }

    /**
     * 以下為 \ArrayAccess, \Countable 的實作
     */

    public function offsetExists($offset): bool
    {
        if (is_int($offset) && 0 <= $offset && $offset < $this->count) {
            return true;
        }
        return false;
    }

    public function offsetGet($offset)
    {
        if (!isset($this->container[$offset])) {
            if ($this->data === null || !is_int($offset) || $offset < 0 || $this->count <= $offset) {
                return null;
            }
            $pos = unpack('N2', $this->data, $offset * 4 + 4);
            $s = $pos[1];
            $e = $pos[2];
            if ($s >= $e) {
                return null;
            }
            $tmp = substr($this->data, $s + $this->count * 4 + 8, $e - $s);
            switch($this->itemClassname) {
                case 'json':
                case 'array':
                    $this->container[$offset] = json_decode($tmp, true);
                    break;
                case 'string':
                    $this->container[$offset] = $tmp;
                    break;
                default:
                    $this->container[$offset] = $this->itemClassname::loadCacheString($tmp);
            }
        }
        return $this->container[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $offset = $this->count;
        }
        if (!is_int($offset) || $offset < 0) {
            throw new \Exception('offset must be int and >= 0');
        }
        $this->container[$offset] = $value;
        if ($offset + 1 > $this->count) {
            $this->count = $offset + 1;
        }
    }

    public function offsetUnset($offset): void
    {
        throw new \Exception('unsupport');
    }

    public function count(): int
    {
        return $this->count;
    }
}
