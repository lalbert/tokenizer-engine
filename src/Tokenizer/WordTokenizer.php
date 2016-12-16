<?php

namespace TokenizerEngine\Tokenizer;

class WordTokenizer implements TokenizerInterface
{
    public function tokenize($data)
    {
        $return = [];

        foreach ((array) $data as $string) {
            $return = array_merge($return, preg_split('/\s+/i', $string, -1, PREG_SPLIT_NO_EMPTY));
        }

        return $return;
    }
}
