<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * Metaphone (Lawrence Philips), using PHP's metaphone() after folding
 * accents. Designed for English.
 */
final class MetaphoneEncoder implements PhoneticEncoderInterface
{
    public function __construct(private readonly int $maxCodeLength = 0)
    {
        if ($maxCodeLength < 0) {
            throw new \InvalidArgumentException('maxCodeLength must be greater than or equal to 0.');
        }
    }

    public function encode(string $word): string
    {
        $letters = Latin::letters($word);

        return '' === $letters ? '' : metaphone($letters, $this->maxCodeLength);
    }
}
