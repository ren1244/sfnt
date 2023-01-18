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
