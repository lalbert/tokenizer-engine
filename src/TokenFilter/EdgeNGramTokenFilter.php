<?php

namespace TokenizerEngine\TokenFilter;

class EdgeNGramTokenFilter implements TokenFilterInterface
{
    private $minGram = 1;
    private $maxGram = 2;
    private $preserveOriginal = true;

    public function __construct($minGram = 1, $maxGram = 2, $preserveOriginal = true)
    {
        $this->minGram = (int) $minGram;
        $this->maxGram = (int) $maxGram;
        $this->preserveOriginal = (bool) $preserveOriginal;

        if ($this->minGram < 1 || $this->maxGram < 1) {
            throw new \InvalidArgumentException('minGram and maxGram must be greater than 0.');
        }

        if ($this->minGram >= $this->maxGram) {
            throw new \InvalidArgumentException('maxGram must be greater than minGram.');
        }
    }

    public function filter(array $tokens)
    {
        $_tokens = [];

        foreach ((array) $tokens as $token) {
            $_tokens = array_merge($_tokens, $this->preserveOriginal ? [$token] : [], $this->edgeNGram($token));
        }

        return $_tokens;
    }

    private function edgeNGram($token)
    {
        $nGrams = [];

        for ($length = $this->minGram; $length <= $this->maxGram; ++$length) {
            $nGram = mb_strcut($token, 0, $length);
            if ($length == mb_strlen($nGram)) {
                $nGrams[] = $nGram;
            }
        }

        return $nGrams;
    }
}
