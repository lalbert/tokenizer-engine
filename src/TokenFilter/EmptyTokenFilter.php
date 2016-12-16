<?php

namespace TokenizerEngine\TokenFilter;

class EmptyTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_filter((array) $tokens);
    }
}
