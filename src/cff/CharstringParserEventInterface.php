<?php

namespace ren1244\sfnt\cff;

interface CharstringParserEventInterface
{    
    /**
     * @param  mixed $x operator 數值
     * @param  mixed $s 對應的字串片段
     * @param  mixed $stack 此時的 stack
     */
    public function onOperator(int $x, string $s, array $stack);    

    /**
     * @param  mixed $x operator 數值
     * @param  mixed $s 對應的字串片段
     */
    public function onNumber(int $x, string $s);

    /**
     * @param bool $openFlag true 表開始(呼叫)，false 表結束(回傳)
     * @param bool $privateFlag true 表 private subr，false 表 global subr
     * @param int $idx 呼叫第幾個
     */
    public function onSubr(bool $openFlag, bool $privateFlag, int $idx);
}
