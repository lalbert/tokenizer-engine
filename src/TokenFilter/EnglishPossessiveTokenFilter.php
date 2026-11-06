<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Removes the trailing possessive 's ("John's" becomes "John"), like
 * Elasticsearch's `stemmer` filter with language `possessive_english`.
 */
final class EnglishPossessiveTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return (string) preg_replace("/['\u{2019}\u{FF07}][sS]$/u", '', $term);
    }
}
