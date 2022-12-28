<?php

namespace ren1244\sfnt\table;

use Exception;
use ren1244\sfnt\TypeReader;

class T_cmap implements TableInterface
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
        $this->version = $reader->readUint(16);
        $this->numTables = $reader->readUint(16);
        $this->encodingRecords = $this->readEncodingRecords();
    }

    public function readEncodingRecords()
    {
        $reader = $this->reader;
        $n = $this->numTables;
        $result = [];
        for ($i = 0; $i < $n; ++$i) {
            $result[] = [
                'platformID' => $reader->readUint(16),
                'encodingID' => $reader->readUint(16),
                'subtableOffset' => $reader->readOffset(32),
            ];
        }
        return $result;
    }

    public function getCodeToGid(int $platformID, int $encodingID, array &$result)
    {
        foreach($this->encodingRecords as $rec) {
            if($rec['platformID'] === $platformID && $rec['encodingID'] === $encodingID) {
                $reader = $this->reader;
                $reader->seek($rec['subtableOffset']);
                $format = $reader->readUint(16);
                $classname = __NAMESPACE__.'\\cmap\\Fmt'.$format;
                if(!class_exists($classname)) {
                    throw new Exception("Class $classname not exists.");
                }
                return $classname::getCodeToGid($reader->createSubReader(
                    $rec['subtableOffset']
                ), $result);
            }
        }
        return null;
    }

    public function __toString() {
        $arr = array_map(function($r){
            $inf = self::INFO[$r['platformID']];
            $plat = $inf['platform'];
            $enc = $inf['encoding'][$r['encodingID']] ?? '無資料';
            $offset = $r['subtableOffset'];
            return "$plat({$r['platformID']}) / $enc({$r['encodingID']}) / $offset";
        }, $this->encodingRecords);
        return implode("\n", $arr);
    }
}
