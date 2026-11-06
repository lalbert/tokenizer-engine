<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Lowercases terms (Unicode aware), like Elasticsearch's `lowercase` filter.
 */
final class LowercaseTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return mb_strtolower($term, 'UTF-8');
    }
}
