<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

interface PhoneticEncoderInterface
{
    /**
     * Returns the phonetic code of a word ('' when nothing can be encoded).
     */
    public function encode(string $word): string;
}
