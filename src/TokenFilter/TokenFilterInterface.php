<?php

namespace TokenizerEngine\TokenFilter;

interface TokenFilterInterface
{
    /**
     * @param array $tokens
     */
    public function filter(array $tokens);
}
