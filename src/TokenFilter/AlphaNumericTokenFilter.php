<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Removes every character that is not a letter, a combining mark or a digit
 * (any script).
 */
final class AlphaNumericTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $term);
    }
}
