<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

/**
 * @internal
 */
final class Digits
{
    /**
     * Converts decimal digits of every script (Arabic-Indic, Devanagari,
     * full-width...) to 0-9.
     */
    public static function toAscii(string $text): string
    {
        if (!preg_match('/[^\x00-\x7F]/', $text)) {
            return $text;
        }

        return (string) preg_replace_callback('/(?![0-9])\p{Nd}/u', static fn (array $m): string => (string) self::value($m[0]), $text);
    }

    private static function value(string $char): int
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
