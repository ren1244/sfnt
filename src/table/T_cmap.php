<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_cmap
{
    const INFO = [
        [
            'platform' => 'Unicode',
            'encoding' => [
                'Unicode 1.0 semantics—deprecated',
                'Unicode 1.1 semantics—deprecated',
                'ISO/IEC 10646 semantics—deprecated',
                'Unicode 2.0 and onwards semantics, Unicode BMP only',
                'Unicode 2.0 and onwards semantics, Unicode full repertoire',
                'Unicode Variation Sequences—for use with subtable format 14',
                'Unicode full repertoire—for use with subtable format 13'
            ]
        ],
        [
            'platform' => 'Macintosh',
            'encoding' => []
        ],
        [
            'platform' => 'ISO [deprecated]',
            'encoding' => ['7-bit ASCII', 'ISO 10646', 'ISO 8859-1']
        ],
        [
            'platform' => 'Windows',
            'encoding' => [
                'Symbol', 'Unicode BMP', 'ShiftJIS', 'PRC', 'Big5',
                'Wansung', 'Johab', 'Reserved', 'Reserved', 'Reserved',
                'Unicode full repertoire'
            ]
        ],
        [
            'platform' => 'Custom',
            'encoding' => []
        ],
    ];

    private $reader;
    private $numTables;
    private $encodingRecords;

    /** @var string unicode(4 byte) + gid(2 byte) 組成陣列 */
    private $cache = null;

    /** @var int cache 共有幾筆資料( = strlen(cache) / 6) */
    private $count;

    private $cacheUTG = [];

    public function __construct(TypeReader $reader)
    {
        $this->reader = $reader;
        $reader->ignore(2); // ignore version
        $this->numTables = $reader->readUint(16);
        $this->encodingRecords = $this->readEncodingRecords();
    }

    public function readEncodingRecords()
    {
        $reader = $this->reader;
        $reader->seek(4);
        $n = $this->numTables;
        $result = [];
        for ($i = 0; $i < $n; ++$i) {
            $platformID = $reader->readUint(16);
            $encodingID = $reader->readUint(16);
            $subtableOffset = $reader->readOffset(32);
            $result["$platformID/$encodingID"] = [
                'platformID' => $platformID,
                'encodingID' => $encodingID,
                'subtableOffset' => $subtableOffset,
            ];
        }
        return $result;
    }

    public function getCodeToGid(int $platformID, int $encodingID, array &$result = null)
    {
        $key = "$platformID/$encodingID";
        if (isset($this->encodingRecords[$key])) {
            $rec = $this->encodingRecords[$key];
            if ($result === null) {
                $result = [];
            }
            $reader = $this->reader;
            $reader->seek($rec['subtableOffset']);
            $format = $reader->readUint(16);
            $classname = __NAMESPACE__ . '\\cmap\\Fmt' . $format;
            if (!class_exists($classname)) {
                throw new Exception("Class $classname not exists.");
            }
            return $classname::getCodeToGid($reader->createSubReader(
                $rec['subtableOffset']
            ), $result);
        }
        return null;
    }

    public function getUnicodeToGid()
    {
        foreach ([[3, 10], [0, 4], [3, 1], [0, 3]] as $rec) {
            if (($codeToGid = $this->getCodeToGid($rec[0], $rec[1])) !== null) {
                return $codeToGid;
            }
        }
        return null;
    }

    /**
     * 產生可用於 cache 的字串
     *
     * @return string
     */
    public function createCache()
    {
        if (($unicodeToGid = $this->getUnicodeToGid()) === null) {
            throw new Exception('cannot find unicode to gid mapping');
        }
        ksort($unicodeToGid, SORT_NUMERIC);
        $cache = '';
        foreach ($unicodeToGid as $code => $gid) {
            $cache .= pack('Nn', $code, $gid);
        }
        return $cache;
    }

    /**
     * 讀取 cache 字串
     *
     * @param  string $cache
     * @return void
     */
    public function loadCache(string $cache)
    {
        $this->cache = $cache;
        $this->count = intval(strlen($cache) / 6);
    }

    /**
     * 取得 unicode 對應的 GID
     * 建議先用 loadCache 載入事先產生的 cache
     * （由於 createCache 計算量較大，應事先計算）
     *
     * @param  int $unicode
     * @return int GID 若找不到時會回傳 0
     */
    public function getGid(int $unicode)
    {
        if ($this->cache === null) {
            $this->loadCache($this->createCache());
        }
        if(isset($this->cacheUTG[$unicode])) {
            return $this->cacheUTG[$unicode];
        }
        $start = 0;
        $end = $this->count - 1;
        $data = $this->cache;
        $pos = $start * 6;
        $startVal = ord($data[$pos]) << 24 | ord($data[$pos + 1]) << 16 | ord($data[$pos + 2]) << 8 | ord($data[$pos + 3]);
        if ($startVal === $unicode) {
            return $this->cacheUTG[$unicode] = ord($data[$pos + 4]) << 8 | ord($data[$pos + 5]);
        }
        $pos = $end * 6;
        $endVal = ord($data[$pos]) << 24 | ord($data[$pos + 1]) << 16 | ord($data[$pos + 2]) << 8 | ord($data[$pos + 3]);
        if ($endVal === $unicode) {
            return $this->cacheUTG[$unicode] = ord($data[$pos + 4]) << 8 | ord($data[$pos + 5]);
        }
        if ($unicode < $startVal || $endVal < $unicode) {
            return $this->cacheUTG[$unicode] = 0;
        }
        // 用二分搜尋法找
        while ($start < $end) {
            $mid = $start + $end + 1 >> 1;
            $pos = $mid * 6;
            $x = ord($data[$pos]) << 24 | ord($data[$pos + 1]) << 16 | ord($data[$pos + 2]) << 8 | ord($data[$pos + 3]);
            if ($x < $unicode) {
                $start = $mid;
            } elseif ($unicode < $x) {
                $end = $mid - 1;
            } else {
                return $this->cacheUTG[$unicode] = ord($data[$pos + 4]) << 8 | ord($data[$pos + 5]);
            }
        }
        return $this->cacheUTG[$unicode] = 0;
    }

    public function __toString()
    {
        $arr = array_map(function ($r) {
            $inf = self::INFO[$r['platformID']];
            $plat = $inf['platform'];
            $enc = $inf['encoding'][$r['encodingID']] ?? '無資料';
            $offset = $r['subtableOffset'];
            return "$plat({$r['platformID']}) / $enc({$r['encodingID']}) / $offset";
        }, $this->encodingRecords);
        return implode("\n", $arr);
    }
}
