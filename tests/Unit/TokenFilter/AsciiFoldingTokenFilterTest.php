<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\AsciiFoldingTokenFilter;

final class AsciiFoldingTokenFilterTest extends TestCase
{
    private const CASES = [
        'Français' => 'Francais',
        'À' => 'A',
        'là' => 'la',
        'Écoute' => 'Ecoute',
        'Île' => 'Ile',
        'l\'œuf' => 'l\'oeuf',
        'Lætitia' => 'Laetitia',
        'Laëtitia' => 'Laetitia',
        'Straße' => 'Strasse',
        'Đặng' => 'Dang',
        'plain' => 'plain',
        '10€' => '10€',
        '£5' => '£5',
    ];

    public function testFoldingWithIntl(): void
    {
        if (!class_exists(\Transliterator::class)) {
            self::markTestSkipped('ext-intl is not installed.');
        }

        self::assertSame(array_values(self::CASES), (new AsciiFoldingTokenFilter())->filter(array_keys(self::CASES)));
    }

    public function testFoldingWithFallbackTable(): void
    {
        self::assertSame(array_values(self::CASES), (new AsciiFoldingTokenFilter(useIntl: false))->filter(array_keys(self::CASES)));
    }

    public function testDecomposedInput(): void
    {
        self::assertSame(['ecole'], (new AsciiFoldingTokenFilter())->filter(["e\u{0301}cole"]));
    }

    public function testPreserveOriginal(): void
    {
        self::assertSame(['café', 'cafe', 'bar'], (new AsciiFoldingTokenFilter(preserveOriginal: true))->filter(['café', 'bar']));
    }

    public function testGermanLocale(): void
    {
        self::assertSame(['Muenchen', 'Strasse'], (new AsciiFoldingTokenFilter('de_DE'))->filter(['München', 'Straße']));
    }

    public function testDanishLocale(): void
    {
        self::assertSame(['Aarhus', 'Oeresund'], (new AsciiFoldingTokenFilter('da'))->filter(['Århus', 'Øresund']));
    }
}
