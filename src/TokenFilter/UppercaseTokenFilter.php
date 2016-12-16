<?php

namespace TokenizerEngine\TokenFilter;

class UppercaseTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_map('mb_strtoupper', (array) $tokens);
    }
}
