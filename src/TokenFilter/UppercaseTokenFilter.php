<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Uppercases terms (Unicode aware, one character at a time: "ß" and "ﬁ"
 * are kept), like Elasticsearch's `uppercase` filter.
 */
final class UppercaseTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return mb_convert_case($term, \MB_CASE_UPPER_SIMPLE, 'UTF-8');
    }
}
