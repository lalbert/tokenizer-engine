<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Digits;

/**
 * Converts decimal digits of every script (Arabic-Indic, Devanagari,
 * full-width...) to 0-9, like Elasticsearch's `decimal_digit` filter.
 */
final class DecimalDigitTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return Digits::toAscii($term);
    }
}
