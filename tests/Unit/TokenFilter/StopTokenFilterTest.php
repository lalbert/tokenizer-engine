<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\StopTokenFilter;

final class StopTokenFilterTest extends TestCase
{
    private const TOKENS = ['foo', 'and', 'et', 'bar'];

    public function testEnglishByDefault(): void
    {
        self::assertSame(['foo', 'et', 'bar'], (new StopTokenFilter())->filter(self::TOKENS));
    }

    public function testFrench(): void
    {
        self::assertSame(['foo', 'and', 'bar'], (new StopTokenFilter('_french_'))->filter(self::TOKENS));
    }

    public function testNone(): void
    {
        self::assertSame(self::TOKENS, (new StopTokenFilter('_none_'))->filter(self::TOKENS));
    }

    public function testCustomList(): void
    {
        self::assertSame(['and', 'et'], (new StopTokenFilter(['foo', 'bar']))->filter(self::TOKENS));
    }

    public function testCustomFile(): void
    {
        self::assertSame(['and', 'et'], (new StopTokenFilter(null, __DIR__.'/../../Fixtures/customStopWords.txt'))->filter(self::TOKENS));
    }

    public function testMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Filename ".*" does not exist\.$/');
        new StopTokenFilter(null, __DIR__.'/../../Fixtures/notExists.txt');
    }

    public function testUnknownList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown stop words list "_klingon_"/');
        new StopTokenFilter('_klingon_');
    }

    public function testIgnoreCase(): void
    {
        $tokens = ['foo', 'and', 'et', 'bar', 'AND'];

        self::assertSame(['foo', 'et', 'bar', 'AND'], (new StopTokenFilter())->filter($tokens));
        self::assertSame(['foo', 'et', 'bar'], (new StopTokenFilter('_english_', null, true))->filter($tokens));
    }

    public function testWholeTermsOnly(): void
    {
        self::assertSame(['the-end', 'theory'], (new StopTokenFilter())->filter(['the-end', 'theory']));
    }

    public function testRemoveTrailing(): void
    {
        self::assertSame(['foo'], (new StopTokenFilter())->filter(['foo', 'the']));
        self::assertSame(['foo', 'the'], (new StopTokenFilter(removeTrailing: false))->filter(['foo', 'and', 'the']));
    }

    public function testAllPredefinedListsLoad(): void
    {
        foreach (StopTokenFilter::availableLists() as $name) {
            $words = StopTokenFilter::predefined($name);
            if ('_none_' !== $name) {
                self::assertGreaterThan(20, \count($words), $name);
                self::assertNotContains('|', $words, $name);
            }
        }

        self::assertContains('_spanish_', StopTokenFilter::availableLists());
    }

    public function testFrenchListIsUtf8(): void
    {
        self::assertContains('même', StopTokenFilter::predefined('_french_'));
        // Snowball deliberately omits "été" (homonym of "summer").
        self::assertNotContains('été', StopTokenFilter::predefined('_french_'));
    }
}
