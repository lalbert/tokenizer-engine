<?php

namespace TokenizerEngine\TokenFilter;

class NGramTokenFilter extends AbstractNGramTokenFilter
{
    protected function nGram($token)
    {
        $nGrams = [];
        $strLen = mb_strlen($token);

        for ($start = 0; $start < $strLen; ++$start) {
            for ($length = $this->minGram; $length <= $this->maxGram; ++$length) {
                $nGram = mb_strcut($token, $start, $length);
                if ($length == mb_strlen($nGram)) {
                    $nGrams[] = $nGram;
                }
            }
        }

        return $nGrams;
    }
}
