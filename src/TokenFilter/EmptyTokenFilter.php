<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Removes empty tokens (but keeps "0").
 */
final class EmptyTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): ?string
    {
        return '' === $term ? null : $term;
    }
}
