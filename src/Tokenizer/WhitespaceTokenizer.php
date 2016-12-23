<?php

namespace TokenizerEngine\Tokenizer;

class WhitespaceTokenizer implements TokenizerInterface
{
    public function tokenize($data)
    {
        $tokens = [];

        foreach ((array) $data as $string) {
            $tokens = array_merge($tokens, preg_split('/\s+/', $string, -1, PREG_SPLIT_NO_EMPTY));
        }

        return $tokens;
    }
}
