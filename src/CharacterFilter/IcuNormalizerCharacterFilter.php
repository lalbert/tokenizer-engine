<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\Util\Icu;

/**
 * Unicode normalization of the whole text before tokenization, like the
 * `icu_normalizer` character filter of Elasticsearch's ICU plugin.
 * Requires ext-intl.
 */
final class IcuNormalizerCharacterFilter implements CharacterFilterInterface
{
    private readonly int $form;

    public function __construct(string $name = 'nfkc_cf', string $mode = 'compose')
    {
        $this->form = Icu::normalizationForm($name, $mode);
    }

    public function filter(string $text): string
    {
        return Icu::normalize($text, $this->form);
    }
}
