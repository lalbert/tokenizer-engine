<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Icu;

/**
 * Unicode normalization (NFC, NFKC or NFKC_Casefold), like the
 * `icu_normalizer` filter of Elasticsearch's ICU plugin. nfkc_cf (default)
 * also folds case and compatibility characters ("ﬁ" becomes "fi", "Ⅻ" becomes
 * "xii"). Requires ext-intl.
 */
final class IcuNormalizerTokenFilter extends AbstractTermTokenFilter
{
    private readonly int $form;

    public function __construct(string $name = 'nfkc_cf', string $mode = 'compose')
    {
        $this->form = Icu::normalizationForm($name, $mode);
    }

    protected function filterTerm(string $term, Token $token): string
    {
        return Icu::normalize($term, $this->form);
    }
}
