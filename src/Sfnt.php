<?php

namespace ren1244\sfnt;

use Exception;

class Sfnt
{
    private $reader;
    private $numTables;
    private $searchRange;
    private $entrySelector;
    private $rangeShift;
    private $tableRecords;
    private $tableCache = [];

    public $sfntVersion;

    public function __construct(TypeReader $reader)
    {
        $this->reader = $reader;
        $this->sfntVersion = $reader->readUint(32);
        $this->numTables = $reader->readUint(16);
        $this->searchRange = $reader->readUint(16);
        $this->entrySelector = $reader->readUint(16);
        $this->rangeShift = $reader->readUint(16);
        $this->tableRecords = $this->readTableRecords();
    }

    public function readTableRecords()
    {
        $reader = $this->reader;
        $n = $this->numTables;
        $result = [];
        for ($i = 0; $i < $n; ++$i) {
            $tag = $reader->readTag();
            $checksum = $reader->readUint(32);
            $offset = $reader->readOffset(32);
            $length = $reader->readUint(32);
            $result[$tag] = [
                'checksum' => $checksum,
                'offset' => $offset,
                'length' => $length,
            ];
        }
        return $result;
    }


    /**
     * get table object
     *
     * @param  mixed $tag
     * @return mixed
     */
    public function table(string $tag)
    {
        if (!isset($this->tableRecords[$tag])) {
            throw new Exception("Table $tag not exists in this font.");
        }
        $classname = __NAMESPACE__ . '\\table\\T_' . str_replace([' ', '/'], '_', $tag);
        if (!class_exists($classname)) {
            throw new Exception("Class $classname not exists.");
        }
        if (!isset($this->tableCache[$tag])) {
            $ref = new \ReflectionClass($classname);
            $refParams = $ref->getConstructor()->getParameters();
            $count = count($refParams);
            $params = [];
            for ($i = 0; $i < $count; ++$i) {
                $type = $refParams[$i]->getType()->getName();
                if ($type === 'ren1244\sfnt\TypeReader') {
                    $params[] = $this->reader->createSubReader(
                        $this->tableRecords[$tag]['offset'],
                        $this->tableRecords[$tag]['length']
                    );
                } else {
                    $params[$i] = $this->table(substr($type, strrpos($type, '\\T_') + 3));
                }
            }
            $this->tableCache[$tag] = $ref->newInstanceArgs($params);
        }
        return $this->tableCache[$tag];
    }

    /**
     * subset TrueType Font
     *
     * @param  array $usedGID ???????????? GID??????????????? [GID => 1, ...]????????????????????? GID ????????????
     * @return string
     */
    public function subset(array $usedGID)
    {
        if (isset($usedGID[0])) {
            unset($usedGID[0]);
        }

        // glyf ??? subset ????????? loca ??????????????????????????? loca subset ?????????
        $subsetedGlyf = $this->table('glyf')->subset($usedGID);

        // ?????? GID = 0???????????? + 1
        $nGlyphs = count($usedGID) + 1;

        // PDF ??????????????????????????? table
        $newTables = [];
        // head: ??????
        $newTables['head'] = $this->table('head')->subset();
        // hhea: ??????????????????
        $newTables['hhea'] = $this->table('hhea')->subset($nGlyphs);
        // hmtx: ????????????
        $newTables['hmtx'] = $this->table('hmtx')->subset($usedGID);
        // loca: glyf ????????????
        $newTables['loca'] = $this->table('loca')->subset();
        // glyf: ???????????????????????????
        $newTables['glyf'] = $subsetedGlyf;
        // maxp: ??????????????????
        $newTables['maxp'] = $this->table('maxp')->subset($nGlyphs);
        // cvt: ???????????????
        $this->copyTableIfExists('cvt ', $newTables);
        // prep: ???????????????
        $this->copyTableIfExists('prep', $newTables);
        // fpgm: ???????????????
        $this->copyTableIfExists('fpgm', $newTables);
        // ??? table ????????? TTF
        $numTables = count($newTables);
        $logTbCount = floor(log($numTables, 2));
        $powOf2 = round(pow(2, $logTbCount));
        $header = [pack('Nn4', 0x00010000, $numTables, $powOf2 * 16, $logTbCount, ($numTables - $powOf2) * 16)];
        $offset = 12 + $numTables * 16;
        foreach ($newTables as $tag => &$content) {
            $len = strlen($content);
            $header[] = $tag . pack('N3', 0, $offset, $len);
            if ($len & 3) {
                $content .= str_repeat(chr(0), 4 - ($len & 3));
                $len += 4 - ($len & 3);
            }
            $offset += $len;
        }
        return implode('', $header) . implode('', $newTables);
    }

    private function copyTableIfExists(string $tag, array &$result)
    {
        if (isset($this->tableRecords[$tag])) {
            $offset = $this->tableRecords[$tag]['offset'];
            $length = $this->tableRecords[$tag]['length'];
            $result[$tag] = $this->reader->slice($offset, $offset + $length);
        }
    }

    public function __toString()
    {
        $result = [];
        foreach ($this->tableRecords as $tag => $info) {
            $offset = $info['offset'];
            $length = $info['length'];
            $result[] = "$tag / $offset / $length";
        }
        return implode("\n", $result);
    }
}
