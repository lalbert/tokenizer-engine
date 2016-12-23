<?php

namespace TokenizerEngine\TokenFilter;

class EdgeNGramTokenFilter extends AbstractNGramTokenFilter
{
    protected function nGram($token)
    {
        $nGrams = [];

        for ($length = $this->minGram; $length <= $this->maxGram; ++$length) {
            $nGram = mb_strcut($token, 0, $length);
            if ($length == mb_strlen($nGram)) {
                $nGrams[] = $nGram;
            }
        }

        return $nGrams;
    }
}
