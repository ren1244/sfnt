<?php

namespace ren1244\sfnt\cache;

/**
 * 儲存 unicode => gid 的列表
 */
class CodeToGid
{
    private $data;
    private $count;

    /**
     * __construct
     *
     * @param  string|array $data 若為 array, 會轉成 string
     * @return void
     */
    public function __construct($data)
    {
        if (is_array($data)) {
            $data = self::stringify($data);
        }
        $this->data = $data;
        $this->count = strlen($data) / 6 | 0;
    }

    /**
     * 轉為 cache 字串
     *
     * @param  array $arraydata unicode => gid 的陣列
     * @return string
     */
    public static function stringify(array $arraydata)
    {
        ksort($arraydata, SORT_NUMERIC);
        $s = '';
        foreach ($arraydata as $code => $gid) {
            $s .= pack('Nn', $code, $gid);
        }
        return $s;
    }
    
    /**
     * 從文字編碼取得 GID
     *
     * @param  int $code 文字編碼
     * @return int GID
     */
    public function getGID($code)
    {
        $start = 0;
        $end = $this->count - 1;
        $data = $this->data;
        $startVal = unpack('N', $data, $start * 6)[1];
        $endVal = unpack('N', $data, $end * 6)[1];
        if($startVal === $code) {
            return unpack('n', $data, $start * 6 + 4)[1];
        } elseif($endVal === $code) {
            return unpack('n', $data, $end * 6 + 4)[1];
        } elseif ($code < $startVal || $endVal < $code) {
            return 0;
        }
        // 用二分搜尋法找
        while ($start < $end) {
            $mid = $start + $end + 1 >> 1;
            $x = unpack('N', $data, $mid * 6)[1];
            if ($x < $code) {
                $start = $mid;
            } elseif ($code < $x) {
                $end = $mid - 1;
            } else {
                return unpack('n', $data, $mid * 6 + 4)[1];
            }
        }
        return 0;
    }
}
