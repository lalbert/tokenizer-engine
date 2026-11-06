<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * American Soundex (Russell & Odell), "Robert" gives R163. Uses PHP's
 * soundex() after folding accents.
 */
final class SoundexEncoder implements PhoneticEncoderInterface
{
    public function encode(string $word): string
    {
        $letters = Latin::letters($word);

        return '' === $letters ? '' : soundex($letters);
    }
}
