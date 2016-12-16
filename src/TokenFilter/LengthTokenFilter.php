<?php

namespace TokenizerEngine\TokenFilter;

class LengthTokenFilter implements TokenFilterInterface
{
    private $min = 0;
    private $max = PHP_INT_MAX;

    public function __construct($min = 0, $max = PHP_INT_MAX)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function filter(array $tokens)
    {
        return array_filter((array) $tokens, [$this, 'length']);
    }

    private function length($token)
    {
        return mb_strlen($token) >= $this->min && mb_strlen($token) <= $this->max;
    }
}
