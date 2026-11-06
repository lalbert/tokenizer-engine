<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Icu;

/**
 * Case folding, accent removal and compatibility normalization in one step
 * ("Ｍüßli" becomes "mussli"), like the `icu_folding` filter of
 * Elasticsearch's ICU plugin (approximation of UTR #30 folding). Requires
 * ext-intl.
 */
final class IcuFoldingTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        return Icu::fold($term);
    }
}
