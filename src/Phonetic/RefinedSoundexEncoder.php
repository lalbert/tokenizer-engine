<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * Refined Soundex (as in Apache Commons Codec and Elasticsearch's
 * `refined_soundex` encoder): "testing" gives T6036084.
 */
final class RefinedSoundexEncoder implements PhoneticEncoderInterface
{
    private const MAPPING = '01360240043788015936020505';

    public function encode(string $word): string
    {
        $letters = Latin::letters($word);
        if ('' === $letters) {
            return '';
        }

        $code = $letters[0];
        $last = null;

        for ($i = 0, $length = \strlen($letters); $i < $length; ++$i) {
            $digit = self::MAPPING[\ord($letters[$i]) - 65];
            if ($digit !== $last) {
                $code .= $digit;
                $last = $digit;
            }
        }

        return $code;
    }
}
