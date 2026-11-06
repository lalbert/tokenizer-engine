<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Lowercases terms (Unicode aware, one character at a time like Java's
 * Character.toLowerCase), like Elasticsearch's `lowercase` filter.
 */
final class LowercaseTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return mb_convert_case($term, \MB_CASE_LOWER_SIMPLE, 'UTF-8');
    }
}
