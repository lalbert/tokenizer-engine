<?php

namespace TokenizerEngine\TokenFilter;

abstract class AbstractNGramTokenFilter implements TokenFilterInterface
{
    protected $minGram = 1;
    protected $maxGram = 2;
    private $preserveOriginal = true;

    public function __construct($minGram = 1, $maxGram = 2, $preserveOriginal = true)
    {
        $this->minGram = (int) $minGram;
        $this->maxGram = (int) $maxGram;
        $this->preserveOriginal = (bool) $preserveOriginal;

        if ($this->minGram < 1 || $this->maxGram < 1) {
            throw new \InvalidArgumentException('minGram and maxGram must be greater than 0.');
        }

        if ($this->minGram > $this->maxGram) {
            throw new \InvalidArgumentException('maxGram must be greater than minGram.');
        }
    }

    public function filter(array $tokens)
    {
        $_tokens = [];

        foreach ((array) $tokens as $token) {
            $_tokens = array_merge($_tokens, $this->preserveOriginal ? [$token] : [], $this->nGram($token));
        }

        return $_tokens;
    }
}
