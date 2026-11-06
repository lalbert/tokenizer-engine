<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Uppercases terms (Unicode aware), like Elasticsearch's `uppercase` filter.
 */
final class UppercaseTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return mb_strtoupper($term, 'UTF-8');
    }
}
