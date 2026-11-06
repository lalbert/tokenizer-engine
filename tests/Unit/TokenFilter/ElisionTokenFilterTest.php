<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\ElisionTokenFilter;

final class ElisionTokenFilterTest extends TestCase
{
    public function testCustomArticles(): void
    {
        self::assertSame(['arbre', 'un'], (new ElisionTokenFilter(['l', 'qu']))->filter(["l'arbre", "qu'un"]));
    }

    public function testFrenchDefaultsAndTypographicApostrophe(): void
    {
        self::assertSame(
            ['avion', 'école', 'à', 'il', 'aujourd\'hui'],
            (new ElisionTokenFilter())->filter(["l'avion", 'l’école', 'jusqu’à', "qu'il", "aujourd'hui"]),
        );
    }

    public function testOnlyAtTheBeginning(): void
    {
        self::assertSame(["ball'"], (new ElisionTokenFilter(['l']))->filter(["ball'"]));
    }

    public function testCase(): void
    {
        self::assertSame(["L'avion"], (new ElisionTokenFilter())->filter(["L'avion"]));
        self::assertSame(['avion'], (new ElisionTokenFilter(null, true))->filter(["L'avion"]));
    }
}
