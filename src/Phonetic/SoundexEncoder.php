<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * American Soundex (Russell & Odell), as implemented by Apache Commons Codec
 * and Elasticsearch: "Robert" gives R163, "Ashcraft" A261, "PHP" P000
 * (letters with the same code separated by H or W are coded once).
 *
 * Accented letters are folded first ("Élodie" gives E430), whereas
 * Elasticsearch leaves words containing them unchanged.
 */
final class SoundexEncoder implements PhoneticEncoderInterface
{
    // A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
    private const MAPPING = '01230120022455012623010202';

    public function encode(string $word): string
    {
        $letters = Latin::letters($word);
        if ('' === $letters) {
            return '';
        }

        $code = $letters[0];
        $last = self::MAPPING[\ord($letters[0]) - 65];

        for ($i = 1, $length = \strlen($letters); $i < $length && \strlen($code) < 4; ++$i) {
            $char = $letters[$i];
            if ('H' === $char || 'W' === $char) {
                continue;
            }

            $digit = self::MAPPING[\ord($char) - 65];
            if ('0' !== $digit && $digit !== $last) {
                $code .= $digit;
            }
            $last = $digit;
        }

        return str_pad($code, 4, '0');
    }
}
