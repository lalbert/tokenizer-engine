<?php

namespace TokenizerEngine\TokenFilter;

class FingerprintTokenFilter implements TokenFilterInterface
{
    private $separator = ' ';
    private $maxOutputSize = 255;

    public function __construct($separator = ' ', $maxOutputSize = 255)
    {
        $this->separator = (string) $separator;
        $this->maxOutputSize = (int) $maxOutputSize;

        if (mb_strlen($this->separator) > 1) {
            throw new \InvalidArgumentException(sprintf('Setting [separator] must be a single, non-null character. [%s] was provided.', $this->separator));
        }
    }

    public function filter(array $tokens)
    {
        $tokens = array_unique($tokens);
        sort($tokens);

        $fingerprint = implode($this->separator, $tokens);

        return mb_strlen($fingerprint) > $this->maxOutputSize ? [] : [$fingerprint];
    }
}
