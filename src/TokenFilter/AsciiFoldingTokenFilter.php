<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\AsciiFoldingMap;

/**
 * Converts alphabetic, numeric and symbolic characters to their ASCII
 * equivalent ("Français" becomes "Francais"), like Elasticsearch's
 * `asciifolding` filter.
 *
 * Uses ICU's Latin-ASCII transliterator when ext-intl is available, otherwise
 * a built-in table. $locale enables language specific rules applied first
 * (e.g. "de": ä becomes ae, "da": å becomes aa).
 */
final class AsciiFoldingTokenFilter extends AbstractTermTokenFilter
{
    private static ?\Transliterator $transliterator = null;

    /**
     * @var array<string, string>
     */
    private readonly array $localeMap;

    public function __construct(
        ?string $locale = null,
        private readonly bool $preserveOriginal = false,
        private readonly bool $useIntl = true,
    ) {
        $this->localeMap = AsciiFoldingMap::forLocale($locale);
    }

    protected function filterTerm(string $term, Token $token): string|array
    {
        $folded = $this->fold($term);

        if ($this->preserveOriginal && $folded !== $term) {
            return [$folded, $term];
        }

        return $folded;
    }

    private function fold(string $term): string
    {
        if (!preg_match('/[^\x00-\x7F]/', $term)) {
            return $term;
        }

        if ([] !== $this->localeMap) {
            $term = strtr($term, $this->localeMap);
        }

        $transliterator = $this->useIntl ? self::transliterator() : null;
        if (null !== $transliterator) {
            $result = $transliterator->transliterate($term);
            if (false !== $result) {
                return $result;
            }
        }

        // Without ICU there is no normalizer: drop combining marks that follow
        // an ASCII letter (decomposed "e\u{0301}" becomes "e").
        $term = (string) preg_replace('/(?<=[A-Za-z])\p{Mn}+/u', '', $term);

        return strtr($term, AsciiFoldingMap::get());
    }

    private static function transliterator(): ?\Transliterator
    {
        if (null === self::$transliterator && class_exists(\Transliterator::class)) {
            self::$transliterator = \Transliterator::create('[^\p{Nl}] NFC; [^\p{Nl}] Latin-ASCII') ?? null;
        }

        return self::$transliterator;
    }
}
