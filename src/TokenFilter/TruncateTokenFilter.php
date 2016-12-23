<?php

namespace TokenizerEngine\TokenFilter;

class TruncateTokenFilter implements TokenFilterInterface
{
    private $length = 10;

    public function __construct($length = 10)
    {
        $this->length = (int) $length;
        if ($this->length <= 0) {
            throw new \InvalidArgumentException('$length must be a positive integer greater than 0.');
        }
    }

    public function filter(array $tokens)
    {
        return array_map([$this, 'length'], (array) $tokens);
    }

    private function length($token)
    {
        return mb_strcut($token, 0, $this->length);
    }
}
