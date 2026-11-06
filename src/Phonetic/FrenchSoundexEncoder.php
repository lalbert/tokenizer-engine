<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * Soundex with the French letter table described in the French Wikipedia
 * article "Soundex": 1 = B P, 2 = C K Q, 3 = D T, 4 = L, 5 = M N, 6 = R,
 * 7 = G J, 8 = S X Z, 9 = F V. "Robert" and "Rupert" give R163.
 *
 * @see https://fr.wikipedia.org/wiki/Soundex
 */
final class FrenchSoundexEncoder implements PhoneticEncoderInterface
{
    private const CODES = [
        'B' => '1', 'P' => '1',
        'C' => '2', 'K' => '2', 'Q' => '2',
        'D' => '3', 'T' => '3',
        'L' => '4',
        'M' => '5', 'N' => '5',
        'R' => '6',
        'G' => '7', 'J' => '7',
        'S' => '8', 'X' => '8', 'Z' => '8',
        'F' => '9', 'V' => '9',
    ];

    public function encode(string $word): string
    {
        $letters = Latin::letters($word);
        if ('' === $letters) {
            return '';
        }

        $code = $letters[0];
        $last = self::CODES[$letters[0]] ?? '';

        for ($i = 1, $length = \strlen($letters); $i < $length && \strlen($code) < 4; ++$i) {
            $char = $letters[$i];

            if ('H' === $char || 'W' === $char) {
                continue; // same digit on both sides of H/W is kept once
            }

            $digit = self::CODES[$char] ?? '';
            if ('' !== $digit && $digit !== $last) {
                $code .= $digit;
            }
            $last = $digit;
        }

        return str_pad($code, 4, '0');
    }
}
