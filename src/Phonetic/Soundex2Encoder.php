<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * "Soundex 2", a French adaptation of Soundex by Frédéric BROUARD
 * (https://sqlpro.developpez.com/cours/soundex/), implemented here from the
 * published description: "GAUTHIER" gives KTR, "BARTHELEMY" gives BRTL.
 *
 * Unlike the original, codes are not padded with spaces.
 */
final class Soundex2Encoder implements PhoneticEncoderInterface
{
    private const GROUPS = ['GUI' => 'KI', 'GUE' => 'KE', 'GA' => 'KA', 'GO' => 'KO', 'GU' => 'K', 'CA' => 'KA', 'CO' => 'KO', 'CU' => 'KU', 'Q' => 'K', 'CC' => 'K', 'CK' => 'K'];
    private const PREFIXES = ['MAC' => 'MCC', 'ASA' => 'AZA', 'KN' => 'NN', 'PF' => 'FF', 'PH' => 'FF', 'SCH' => 'SSS'];

    public function encode(string $word): string
    {
        $s = Latin::letters($word, ['Ç' => 'S', 'Œ' => 'E']);
        if (\strlen($s) <= 1) {
            return $s;
        }

        // Primary groups, in table order.
        foreach (self::GROUPS as $from => $to) {
            $s = str_replace($from, $to, $s);
        }

        // Vowels (except Y) become A, the first letter excepted.
        $s = $s[0].strtr(substr($s, 1), 'EIOU', 'AAAA');

        // Prefixes.
        foreach (self::PREFIXES as $from => $to) {
            if (str_starts_with($s, $from)) {
                $s = $to.substr($s, \strlen($from));
                break;
            }
        }

        // H is kept only after C or S, Y only after A.
        $s = (string) preg_replace(['/(?<![CS])H/', '/(?<!A)Y/'], '', $s);

        // Terminal A, D, S or T.
        $s = (string) preg_replace('/[ADST]$/', '', $s);

        // A is removed, except at the beginning.
        $s = '' === $s ? '' : $s[0].str_replace('A', '', substr($s, 1));

        // Repeated letters.
        $s = (string) preg_replace('/(.)\1+/', '$1', $s);

        return substr($s, 0, 4);
    }
}
