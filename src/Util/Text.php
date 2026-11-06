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

    /**
     * Wraps a user supplied regular expression body (Elasticsearch style,
     * without delimiters) into a PCRE pattern with Unicode semantics.
     */
    public static function pattern(string $pattern, string $flags = ''): string
    {
        $modifiers = 'u'.preg_replace('/[^imsxU]/', '', $flags);
        $regex = '~(*UCP)'.str_replace('~', '\~', $pattern).'~'.$modifiers;

        if (false === @preg_match($regex, '')) {
            throw new \InvalidArgumentException(\sprintf('Invalid regular expression "%s".', $pattern));
        }

        return $regex;
    }
}
