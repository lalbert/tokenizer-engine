<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

/**
 * Kölner Phonetik (Hans Joachim Postel, 1969), the German equivalent of
 * Soundex, like Elasticsearch's `koelnerphonetik`/`cologne` encoders:
 * "Müller-Lüdenscheidt" gives 65752682.
 */
final class ColognePhoneticEncoder implements PhoneticEncoderInterface
{
    public function encode(string $word): string
    {
        $s = Latin::letters($word, ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'ß' => 'S', 'ẞ' => 'S']);
        $length = \strlen($s);
        $raw = '';

        for ($i = 0; $i < $length; ++$i) {
            $c = $s[$i];
            $prev = $i > 0 ? $s[$i - 1] : '';
            $next = $s[$i + 1] ?? '';

            $raw .= match (true) {
                str_contains('AEIJOUY', $c) => '0',
                'H' === $c => '',
                'B' === $c => '1',
                'P' === $c => 'H' === $next ? '3' : '1',
                'D' === $c, 'T' === $c => str_contains('CSZ', $next) && '' !== $next ? '8' : '2',
                str_contains('FVW', $c) => '3',
                str_contains('GKQ', $c) => '4',
                'C' === $c => $this->c($i, $prev, $next) ? '4' : '8',
                'X' === $c => str_contains('CKQ', $prev) && '' !== $prev ? '8' : '48',
                'L' === $c => '5',
                'M' === $c, 'N' === $c => '6',
                'R' === $c => '7',
                default => '8', // S, Z
            };
        }

        // Collapse repeated digits, then drop "0" except at the beginning.
        $collapsed = (string) preg_replace('/(\d)\1+/', '$1', $raw);

        return '' === $collapsed ? '' : $collapsed[0].str_replace('0', '', substr($collapsed, 1));
    }

    private function c(int $i, string $prev, string $next): bool
    {
        if ('' === $next) {
            return false;
        }

        if (0 === $i) {
            return str_contains('AHKLOQRUX', $next);
        }

        return str_contains('AHKOQUX', $next) && !('' !== $prev && str_contains('SZ', $prev));
    }
}
