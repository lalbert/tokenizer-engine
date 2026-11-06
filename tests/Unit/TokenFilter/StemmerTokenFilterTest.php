<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;

final class StemmerTokenFilterTest extends TestCase
{
    public function testFrench(): void
    {
        self::assertSame(['cheval', 'continuel', 'national'], (new StemmerTokenFilter('french'))->filter(['chevaux', 'continuellement', 'nationales']));
    }

    public function testEnglishAliases(): void
    {
        foreach (['english', 'en', 'porter', 'light_english'] as $language) {
            self::assertSame(['run', 'fox'], (new StemmerTokenFilter($language))->filter(['running', 'foxes']), $language);
        }
    }

    public function testUnknownLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StemmerTokenFilter('klingon');
    }
}
