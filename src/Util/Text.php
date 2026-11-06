<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

/**
 * UTF-8 helpers.
 *
 * @internal
 */
final class Text
{
    /**
     * Splits a string into user-perceived characters (extended grapheme
     * clusters), so that combining marks stay attached to their base letter.
     *
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        if ('' === $text) {
            return [];
        }

        if (false === preg_match_all('/\X/u', $text, $matches)) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        return $matches[0];
    }

    /**
     * Converts a byte offset into a code point offset.
     */
    public static function charOffset(string $text, int $byteOffset): int
    {
        return mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
    }

    /**
     * Levenshtein distance computed on code points (PHP's levenshtein() works
     * on bytes, so "é" vs "e" would count as 2 edits).
     */
    public static function levenshtein(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $lenA = \count($a);
        $lenB = \count($b);

        if (0 === $lenA) {
            return $lenB;
        }
        if (0 === $lenB) {
            return $lenA;
        }

        $previous = range(0, $lenB);
        for ($i = 1; $i <= $lenA; ++$i) {
            $current = [$i];
            for ($j = 1; $j <= $lenB; ++$j) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $current[$j] = min($previous[$j] + 1, $current[$j - 1] + 1, $previous[$j - 1] + $cost);
            }
            $previous = $current;
        }

        return $previous[$lenB];
    }

    private const ASCII_CLASSES = [
        'w' => '[A-Za-z0-9_]', 'W' => '[^A-Za-z0-9_]',
        'd' => '[0-9]', 'D' => '[^0-9]',
        's' => '[ \t\n\x0B\f\r]', 'S' => '[^ \t\n\x0B\f\r]',
        'b' => '(?:(?<=[A-Za-z0-9_])(?![A-Za-z0-9_])|(?<![A-Za-z0-9_])(?=[A-Za-z0-9_]))',
        'B' => '(?:(?<=[A-Za-z0-9_])(?=[A-Za-z0-9_])|(?<![A-Za-z0-9_])(?![A-Za-z0-9_]))',
    ];

    /**
     * Inside a character class only the positive shorthands can be expanded.
     */
    private const ASCII_CLASS_RANGES = ['w' => 'A-Za-z0-9_', 'd' => '0-9', 's' => ' \t\n\x0B\f\r'];

    private const JAVA_FLAGS = ['CASE_INSENSITIVE' => 'i', 'COMMENTS' => 'x', 'DOTALL' => 's', 'MULTILINE' => 'm'];

    /**
     * Wraps a user supplied regular expression body (Elasticsearch style,
     * without delimiters) into a PCRE pattern.
     *
     * Like Java (and Elasticsearch), \w, \d, \s and \b only match ASCII
     * characters unless the UNICODE_CHARACTER_CLASS flag or a leading (?U) is
     * given; \p{L} and friends always work on Unicode.
     *
     * $flags: Java flag names separated by "|" (CASE_INSENSITIVE|COMMENTS|
     * DOTALL|MULTILINE|UNICODE_CHARACTER_CLASS), or PCRE modifier letters
     * (i, m, s, x).
     */
    public static function pattern(string $pattern, string $flags = ''): string
    {
        $unicodeClasses = false;
        if (str_starts_with($pattern, '(?U)')) {
            $unicodeClasses = true;
            $pattern = substr($pattern, 4);
        }

        if (preg_match('/[A-Z_]{2,}/', $flags)) {
            $modifiers = '';
            foreach (preg_split('/\s*\|\s*/', $flags, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $flag) {
                $flag = strtoupper($flag);
                $unicodeClasses = $unicodeClasses || 'UNICODE_CHARACTER_CLASS' === $flag;
                $modifiers .= self::JAVA_FLAGS[$flag] ?? '';
            }
        } else {
            $modifiers = (string) preg_replace('/[^imsx]/', '', $flags);
        }

        // PHP's "u" modifier makes \w and friends Unicode aware (PCRE2_UCP):
        // rewrite them as ASCII classes unless Unicode classes were requested.
        $body = $unicodeClasses ? $pattern : self::asciiClasses($pattern);
        $regex = '~'.str_replace('~', '\~', $body).'~u'.$modifiers;

        if (false === @preg_match($regex, '')) {
            throw new \InvalidArgumentException(\sprintf('Invalid regular expression "%s".', $pattern));
        }

        return $regex;
    }

    private static function asciiClasses(string $pattern): string
    {
        $result = '';
        $inClass = false;
        $length = \strlen($pattern);

        for ($i = 0; $i < $length; ++$i) {
            $char = $pattern[$i];

            if ('\\' === $char && $i + 1 < $length) {
                $next = $pattern[++$i];
                if ($inClass) {
                    $result .= isset(self::ASCII_CLASS_RANGES[$next]) ? self::ASCII_CLASS_RANGES[$next] : '\\'.$next;
                } else {
                    $result .= self::ASCII_CLASSES[$next] ?? '\\'.$next;
                }
                continue;
            }

            if ('[' === $char && !$inClass) {
                $inClass = true;
                $result .= $char;
                // A "]" right after "[" or "[^" is a literal.
                if ($i + 1 < $length && '^' === $pattern[$i + 1]) {
                    $result .= $pattern[++$i];
                }
                if ($i + 1 < $length && ']' === $pattern[$i + 1]) {
                    $result .= $pattern[++$i];
                }
                continue;
            }

            if (']' === $char && $inClass) {
                $inClass = false;
            }

            $result .= $char;
        }

        return $result;
    }
}
