<?php

namespace TokenizerEngine\TokenFilter;

class AlphaNumericTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return  preg_replace('/[^A-Za-z0-9]/', '', (array) $tokens);
    }
}
