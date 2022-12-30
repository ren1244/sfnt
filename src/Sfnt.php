<?php
namespace ren1244\sfnt;

use Exception;

class sfnt
{
    private $reader;
    private $sfntVersion;
    private $numTables;
    private $searchRange;
    private $entrySelector;
    private $rangeShift;
    private $tableRecords;
    private $tableCache = [];

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

    public function readTableRecords() {
        $reader = $this->reader;
        $n = $this->numTables;
        $result = [];
        for($i=0;$i<$n;++$i) {
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
     * table
     *
     * @param  mixed $tag
     * @return mixed
     */
    public function table(string $tag) {
        if(!isset($this->tableRecords[$tag])) {
            throw new Exception("Table $tag not exists in this font.");
        }
        $classname = __NAMESPACE__.'\\table\\T_'.str_replace([' ', '/'], '_', $tag);
        if(!class_exists($classname)) {
            throw new Exception("Class $classname not exists.");
        }
        if(!isset($this->tableCache[$tag])) {
            $this->tableCache[$tag] = new $classname($this->reader->createSubReader(
                $this->tableRecords[$tag]['offset'],
                $this->tableRecords[$tag]['length']
            ));
        }
        return $this->tableCache[$tag];
    }

    public function __toString() {
        $result = [];
        foreach($this->tableRecords as $tag => $info) {
            $offset = $info['offset'];
            $length = $info['length'];
            $result[] = "$tag / $offset/ $length";
        }
        return implode("\n", $result);
    }
}