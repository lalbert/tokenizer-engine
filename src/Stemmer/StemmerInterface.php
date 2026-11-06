<?php

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

interface StemmerInterface
{
    /**
     * @param string $word a lowercased word
     */
    public function stem(string $word): string;
}
