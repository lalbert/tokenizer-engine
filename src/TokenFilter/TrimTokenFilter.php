<?php

namespace TokenizerEngine\TokenFilter;

class TrimTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_map('trim', (array) $tokens);
    }
}
