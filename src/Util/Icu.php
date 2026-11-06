<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

/**
 * ext-intl helpers.
 *
 * @internal
 */
final class Icu
{
    private static ?\Transliterator $folding = null;

    public static function normalizationForm(string $name, string $mode = 'compose'): int
    {
        self::requireIntl();

        if (!\in_array($mode, ['compose', 'decompose'], true)) {
            throw new \InvalidArgumentException('mode must be "compose" or "decompose".');
        }

        return match ([strtolower($name), $mode]) {
            ['nfc', 'compose'] => \Normalizer::FORM_C,
            ['nfc', 'decompose'] => \Normalizer::FORM_D,
            ['nfkc', 'compose'] => \Normalizer::FORM_KC,
            ['nfkc', 'decompose'] => \Normalizer::FORM_KD,
            ['nfkc_cf', 'compose'] => self::formKcCf(),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported normalization "%s" (mode %s).', $name, $mode)),
        };
    }

    public static function normalize(string $text, int $form): string
    {
        $result = \Normalizer::normalize($text, $form);
        if (false === $result) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        return $result;
    }

    public static function fold(string $text): string
    {
        self::requireIntl();

        self::$folding ??= \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Latin-ASCII')
            ?? throw new \LogicException('Unable to create the ICU folding transliterator.');

        $folded = self::$folding->transliterate(self::normalize($text, self::formKcCf()));
        if (false === $folded) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        // UTR #30 folding also maps decimal digits of every script to 0-9.
        return Digits::toAscii(self::normalize($folded, self::formKcCf()));
    }

    /**
     * Normalizer::FORM_KC_CF (PHP 7.3+ with ICU 56+), read dynamically
     * because static analysis stubs do not always declare it.
     */
    private static function formKcCf(): int
    {
        $constant = \Normalizer::class.'::FORM_KC_CF';
        if (!\defined($constant)) {
            throw new \LogicException('NFKC_Casefold needs ICU 56 or later.');
        }

        return (int) \constant($constant);
    }

    private static function requireIntl(): void
    {
        if (!\extension_loaded('intl')) {
            throw new \LogicException('ICU components require the intl extension.');
        }
    }
}
