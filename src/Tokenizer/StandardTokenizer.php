<?php

namespace TokenizerEngine\Tokenizer;

class StandardTokenizer implements TokenizerInterface
{
    private $maxTokenLength;

    public function __construct($maxTokenLength = 255)
    {
        $this->maxTokenLength = $maxTokenLength;
    }

    public function tokenize($data)
    {
        $_tokens = [];

        foreach ((array) $data as $string) {
            $_tokens = array_merge($_tokens, preg_split('/[\s-]+/i', $string, -1, PREG_SPLIT_NO_EMPTY));
        }

        $tokens = [];
        foreach ($_tokens as $token) {
            $tokens = array_merge($tokens, str_split($token, $this->maxTokenLength));
        }

        return $tokens;
    }
}
