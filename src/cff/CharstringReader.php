<?php

namespace ren1244\sfnt\cff;

class CharstringReader implements CharstringParserEventInterface
{
    const OPERATORS = [
        -37 => 'flex1',
        -36 => 'hflex1',
        -35 => 'flex',
        -34 => 'hflex',
        -30 => 'roll',
        -29 => 'index',
        -28 => 'exch', // [R] swap(a,b)
        -27 => 'dup', // [RR] a（拿出一次放回兩次=>複製頂層元素一次）
        -26 => 'sqrt', // [R] sqrt(a)
        -24 => 'mul', // [R] a * b
        -23 => 'random', // [R]
        -22 => 'ifelse',
        -21 => 'get',
        -20 => 'put',
        -18 => 'drop', // 移除 1 個
        -15 => 'eq', // [R] a == b
        -14 => 'neg', // [R] -a
        -12 => 'div', // [R] a / b
        -11 => 'sub', // [R] a - b
        -10 => 'add', // [R] a + b
        -9 => 'abs', // [R] abs(a)
        -5 => 'not', // [R] !a
        -4 => 'or',  // [R] a || b
        -3 => 'and', // [R] a && b
        //
        1 => 'hstem', // stem, |-
        3 => 'vstem', // stem, |-
        4 => 'vmoveto', // start |-
        5 => 'rlineto', // |-
        6 => 'hlineto', // |-
        7 => 'vlineto', // |-
        8 => 'rrcurveto', // |-
        10 => 'callsubr',
        11 => 'return',
        14 => 'endchar', // start |-
        18 => 'hstemhm', // stem, |-
        19 => 'hintmask', // stem, mask |-
        20 => 'cntrmask', // stem, mask |-
        21 => 'rmoveto', // start |-
        22 => 'hmoveto', // start |-
        23 => 'vstemhm', // stem, |-
        24 => 'rcurveline', // |-
        25 => 'rlinecurve', // |-
        26 => 'vvcurveto', // |-
        27 => 'hhcurveto', // |-
        29 => 'callgsubr',
        30 => 'vhcurveto', // |-
        31 => 'hvcurveto', // |-
    ];

    private $log = [];
    private $line = [];
    private $indent = 0;
    private $usedSubrs = [];
    private $usedGSubrs = [];

    public function onOperator(int $x, string $s, array $stack)
    {
        $op = self::OPERATORS[$x] ?? '??';
        $this->line[] = "$op(" . bin2hex($s) . ")";
        if ($x !== 29 && $x !== 10 && $x !== 11) {
            $this->endline();
        }
    }

    public function onNumber(int $x, string $s)
    {
        $this->line[] = "$x(" . bin2hex($s) . ")";
    }

    public function onSubr(bool $openFlag, bool $privateFlag, int $idx)
    {
        if ($openFlag) {
            $this->line[] = '{';
            $this->indent += 1;
            $this->endline();
            if ($privateFlag) {
                $this->usedSubrs[$idx] = true;
            } else {
                $this->usedGSubrs[$idx] = true;
            }
        } else {
            $this->indent -= 1;
            $this->endline();
            $this->line[] = '}';
            $this->endline();
        }
    }

    public function output()
    {
        if (count($this->line) > 0) {
            $this->endline();
        }
        return implode("\n", $this->log);
    }

    public function getUsedSubrs() {
        return [
            'g' => $this->usedGSubrs,
            'p' => $this->usedSubrs,
        ];
    }

    private function endline()
    {
        $this->log[] = implode(' ', $this->line);
        $this->line = [str_repeat(' ', $this->indent * 4)];
    }
}
