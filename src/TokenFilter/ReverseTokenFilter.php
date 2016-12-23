<?php

namespace TokenizerEngine\TokenFilter;

class ReverseTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_map('strrev', $tokens);
    }
}
