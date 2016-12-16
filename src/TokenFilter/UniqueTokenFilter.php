<?php

namespace TokenizerEngine\TokenFilter;

class UniqueTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_unique((array) $tokens);
    }
}
