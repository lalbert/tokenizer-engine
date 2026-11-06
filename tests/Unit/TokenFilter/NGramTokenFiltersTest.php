<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\EdgeNGramTokenFilter;
use TokenizerEngine\TokenFilter\NGramTokenFilter;

final class NGramTokenFiltersTest extends TestCase
{
    public function testNGram(): void
    {
        self::assertSame(
            ['Hello', 'H', 'He', 'e', 'el', 'l', 'll', 'l', 'lo', 'o', 'world !', 'w', 'wo', 'o', 'or', 'r', 'rl', 'l', 'ld', 'd', 'd ', ' ', ' !', '!'],
            (new NGramTokenFilter())->filter(['Hello', 'world !']),
        );
    }

    public function testNGramWithoutOriginal(): void
    {
        self::assertSame(['f', 'fo', 'o', 'ox', 'x'], (new NGramTokenFilter(1, 2, false))->filter(['fox']));
    }

    public function testNGramMinMax(): void
    {
        self::assertSame(
            ['Hello', 'He', 'Hel', 'Hell', 'el', 'ell', 'ello', 'll', 'llo', 'lo'],
            (new NGramTokenFilter(2, 4))->filter(['Hello']),
        );
        self::assertSame(['Hello', 'Hel', 'ell', 'llo'], (new NGramTokenFilter(3, 3))->filter(['Hello']));
    }

    public function testNGramUtf8(): void
    {
        self::assertSame(['é', 'ét', 't', 'té', 'é'], (new NGramTokenFilter(1, 2, false))->filter(['été']));
    }

    public function testOriginalIsNotDuplicated(): void
    {
        self::assertSame(['a', 'ab', 'b'], (new NGramTokenFilter(1, 2))->filter(['ab']));
    }

    public function testEdgeNGram(): void
    {
        self::assertSame(['Hello', 'H', 'He', 'world !', 'w', 'wo'], (new EdgeNGramTokenFilter())->filter(['Hello', 'world !']));
        self::assertSame(['H', 'He', 'w', 'wo'], (new EdgeNGramTokenFilter(1, 2, false))->filter(['Hello', 'world !']));
        self::assertSame(['Hello', 'He', 'Hel', 'Hell'], (new EdgeNGramTokenFilter(2, 4))->filter(['Hello']));
        self::assertSame(['Hello', 'Hel'], (new EdgeNGramTokenFilter(3, 3))->filter(['Hello']));
    }

    public function testEdgeNGramUtf8(): void
    {
        self::assertSame(['é', 'él', 'éla'], (new EdgeNGramTokenFilter(1, 3, false))->filter(['élan']));
    }

    public function testTermShorterThanMinGram(): void
    {
        self::assertSame([], (new EdgeNGramTokenFilter(3, 4, false))->filter(['ab']));
        self::assertSame(['ab'], (new EdgeNGramTokenFilter(3, 4, true))->filter(['ab']));
    }

    /**
     * @return iterable<array{int, int}>
     */
    public static function invalidBounds(): iterable
    {
        yield [0, 0];
        yield [0, 2];
        yield [2, 1];
    }

    #[DataProvider('invalidBounds')]
    public function testInvalidBounds(int $min, int $max): void
    {
        foreach ([NGramTokenFilter::class, EdgeNGramTokenFilter::class] as $class) {
            try {
                new $class($min, $max);
                self::fail('An exception was expected.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
