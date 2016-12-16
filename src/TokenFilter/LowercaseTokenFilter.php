<?php

namespace TokenizerEngine\TokenFilter;

class LowercaseTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_map('mb_strtolower', (array) $tokens);
    }
}
