<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Strips the apostrophe and everything after it, like Elasticsearch's
 * `apostrophe` filter. Handles both ' and ’ (U+2019).
 */
final class ApostropheTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return (string) preg_replace("/['\u{2019}].*$/su", '', $term);
    }
}
