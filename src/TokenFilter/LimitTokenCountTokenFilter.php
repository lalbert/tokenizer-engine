<?php

namespace TokenizerEngine\TokenFilter;

class LimitTokenCountTokenFilter implements TokenFilterInterface
{
    private $maxTokenCount = 1;

    public function __construct($maxTokenCount = 1)
    {
        $this->maxTokenCount = (int) $maxTokenCount;
        if ($this->maxTokenCount <= 0) {
            throw new \InvalidArgumentException('$maxTokenCount must be greater than zero.');
        }
    }

    public function filter(array $tokens)
    {
        return array_slice((array) $tokens, 0, $this->maxTokenCount);
    }
}
