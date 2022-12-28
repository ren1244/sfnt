<?php

namespace ren1244\sfnt\cff;

class CharstringRecoder implements CharstringParserEventInterface
{
    public $usedGsubrs = [];
    public $privateDict = null;

    public function onOperator(int $x, string $s, array $stack)
    {
        
    }

    public function onNumber(int $x, string $s)
    {
        
    }

    public function onSubr(bool $openFlag, bool $privateFlag, int $idx)
    {
        if($openFlag) {
            if($privateFlag) {
                if($this->privateDict) {
                    $this->privateDict->setUsed($idx);
                }
            } else {
                $this->usedGsubrs[$idx] = true;
            }
        }
    }
}
