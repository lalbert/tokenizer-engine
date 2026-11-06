<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Converts decimal digits of every script (Arabic-Indic, Devanagari,
 * full-width...) to 0-9, like Elasticsearch's `decimal_digit` filter.
 */
final class DecimalDigitTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        if (!preg_match('/[^\x00-\x7F]/', $term)) {
            return $term;
        }

        return (string) preg_replace_callback('/(?![0-9])\p{Nd}/u', static fn (array $m): string => (string) self::digitValue($m[0]), $term);
    }

    private static function digitValue(string $char): int
    {
        $codePoint = (int) mb_ord($char, 'UTF-8');

        if (class_exists(\IntlChar::class)) {
            return (int) \IntlChar::charDigitValue($codePoint);
        }

        // Unicode decimal digits come in contiguous runs of ten (0 to 9).
        $start = $codePoint;
        while ($start > 0 && 1 === preg_match('/^\p{Nd}$/u', (string) mb_chr($start - 1, 'UTF-8'))) {
            --$start;
        }

        return ($codePoint - $start) % 10;
    }
}
