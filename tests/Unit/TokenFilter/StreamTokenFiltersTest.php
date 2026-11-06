<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\FingerprintTokenFilter;
use TokenizerEngine\TokenFilter\LengthTokenFilter;
use TokenizerEngine\TokenFilter\LimitTokenCountTokenFilter;
use TokenizerEngine\TokenFilter\TruncateTokenFilter;
use TokenizerEngine\TokenFilter\UniqueTokenFilter;

final class StreamTokenFiltersTest extends TestCase
{
    private const NUMBERS = ['1', '22', '333', '4444', '55555', '666666', '7777777', '88888888', '999999999'];

    public function testLength(): void
    {
        self::assertCount(9, (new LengthTokenFilter())->filter(self::NUMBERS));
        self::assertSame(['1', '22', '333', '4444', '55555'], (new LengthTokenFilter(0, 5))->filter(self::NUMBERS));
        self::assertSame(['55555', '666666', '7777777'], (new LengthTokenFilter(5, 7))->filter(self::NUMBERS));
        self::assertSame(['été'], (new LengthTokenFilter(3, 3))->filter(['été', 'ét']));
    }

    public function testInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LengthTokenFilter(5, 2);
    }

    public function testTruncate(): void
    {
        self::assertSame(['foo', 'averylongw', 'bar'], (new TruncateTokenFilter())->filter(['foo', 'averylongword', 'bar']));
        self::assertSame(['fo', 'av', 'ét'], (new TruncateTokenFilter(2))->filter(['foo', 'averylongword', 'été']));
    }

    public function testInvalidTruncate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TruncateTokenFilter(0);
    }

    public function testLimit(): void
    {
        self::assertSame(['foo'], (new LimitTokenCountTokenFilter())->filter(['foo', 'bar', 'baz']));
        self::assertSame(['foo', 'bar'], (new LimitTokenCountTokenFilter(2))->filter(['foo', 'bar', 'baz']));
    }

    public function testInvalidLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LimitTokenCountTokenFilter(0);
    }

    public function testUnique(): void
    {
        self::assertSame(['foo', 'bar', 'baz'], (new UniqueTokenFilter())->filter(['foo', 'bar', 'foo', 'bar', 'baz']));
    }

    public function testUniqueOnSamePosition(): void
    {
        $tokens = [new Token('a', 0), new Token('a', 0), new Token('a', 1)];
        $result = (new UniqueTokenFilter(true))->filter($tokens);

        self::assertSame([0, 1], array_map(static fn (Token $t): int => $t->position, $result));
    }

    public function testFingerprint(): void
    {
        $tokens = ['the', 'quick', 'quick', 'brown', 'fox', 'was', 'very', 'brown'];

        self::assertSame(['brown fox quick the very was'], (new FingerprintTokenFilter())->filter($tokens));
        self::assertSame(['brown-fox-quick-the-very-was'], (new FingerprintTokenFilter('-'))->filter($tokens));
        self::assertSame([], (new FingerprintTokenFilter(' ', 5))->filter($tokens));
    }

    public function testFingerprintBadSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting [separator] must be a single, non-null character. [--] was provided.');
        new FingerprintTokenFilter('--');
    }
}
