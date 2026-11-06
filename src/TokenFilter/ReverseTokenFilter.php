<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Text;

/**
 * Reverses each term, like Elasticsearch's `reverse` filter. Works on
 * grapheme clusters, so accents and combining marks are preserved.
 */
final class ReverseTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return implode('', array_reverse(Text::graphemes($term)));
    }
}
