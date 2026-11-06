<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\Util\Icu;
use TokenizerEngine\Util\OffsetRewriter;

/**
 * Unicode normalization of the whole text before tokenization, like the
 * `icu_normalizer` character filter of Elasticsearch's ICU plugin.
 * Requires ext-intl.
 */
final class IcuNormalizerCharacterFilter implements OffsetMappingCharacterFilterInterface
{
    private readonly int $form;

    public function __construct(string $name = 'nfkc_cf', string $mode = 'compose')
    {
        $this->form = Icu::normalizationForm($name, $mode);
    }

    public function filterWithOffsets(string $text): array
    {
        // Normalized one grapheme cluster at a time so that offsets can be mapped.
        return OffsetRewriter::rewrite($text, '/\X/u', function (array $m): ?array {
            $normalized = Icu::normalize($m[0], $this->form);

            return $normalized === $m[0] ? null : [$normalized, false];
        });
    }

    public function filter(string $text): string
    {
        return Icu::normalize($text, $this->form);
    }
}
