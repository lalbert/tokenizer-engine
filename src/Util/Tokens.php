<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

use TokenizerEngine\Token;

/**
 * Helpers to convert between plain strings and Token objects.
 *
 * @internal
 */
final class Tokens
{
    /**
     * @param array<array-key, string|\Stringable|Token> $items
     */
    public static function containsTokenObjects(array $items): bool
    {
        foreach ($items as $item) {
            if ($item instanceof Token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wraps every item into a Token. Bare strings get their index as position.
     *
     * @param array<array-key, string|\Stringable|Token> $items
     *
     * @return list<Token>
     */
    public static function normalize(array $items): array
    {
        $tokens = [];
        $position = 0;

        foreach ($items as $item) {
            $tokens[] = $item instanceof Token ? $item : new Token((string) $item, $position);
            ++$position;
        }

        return $tokens;
    }

    /**
     * @param iterable<Token> $tokens
     *
     * @return list<string>
     */
    public static function terms(iterable $tokens): array
    {
        $terms = [];
        foreach ($tokens as $token) {
            $terms[] = $token->term;
        }

        return $terms;
    }
}
