<?php

namespace TokenizerEngine\TokenFilter;

class ApostropheTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens)
    {
        return array_map([$this, 'apostrophe'], (array) $tokens);
    }

    private function apostrophe($token)
    {
        return trim(preg_replace("/('(?:.*))/", '', $token));
    }
}
