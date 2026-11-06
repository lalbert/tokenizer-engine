<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\AlphaNumericTokenFilter;
use TokenizerEngine\TokenFilter\ApostropheTokenFilter;
use TokenizerEngine\TokenFilter\EmptyTokenFilter;
use TokenizerEngine\TokenFilter\EnglishPossessiveTokenFilter;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\ReverseTokenFilter;
use TokenizerEngine\TokenFilter\TokenFilterInterface;
use TokenizerEngine\TokenFilter\TrimTokenFilter;
use TokenizerEngine\TokenFilter\UppercaseTokenFilter;

/**
 * Filters without options: one data set per (filter, input, expected).
 */
final class SimpleTokenFiltersTest extends TestCase
{
    /**
     * @return iterable<string, array{TokenFilterInterface, list<string>, list<string>}>
     */
    public static function provider(): iterable
    {
        yield 'lowercase' => [new LowercaseTokenFilter(), ['FOO', 'ÉCOLE', 'Straße'], ['foo', 'école', 'straße']];
        yield 'uppercase' => [new UppercaseTokenFilter(), ['foo', 'école'], ['FOO', 'ÉCOLE']];
        yield 'trim' => [new TrimTokenFilter(), ['  a  ', "b\u{00A0}", "\tc"], ['a', 'b', 'c']];
        yield 'empty keeps "0"' => [new EmptyTokenFilter(), ['a', '', '  ', '0', 'b'], ['a', '  ', '0', 'b']];
        yield 'reverse' => [new ReverseTokenFilter(), ['Hello', 'world'], ['olleH', 'dlrow']];
        yield 'reverse utf-8' => [new ReverseTokenFilter(), ['été', "e\u{0301}a"], ['été', "ae\u{0301}"]];
        yield 'alphanumeric' => [new AlphaNumericTokenFilter(), ['Foo', 'Bar', 'Foo-Bar', 'Foo|Bar', 'Élève!'], ['Foo', 'Bar', 'FooBar', 'FooBar', 'Élève']];
        yield 'apostrophe' => [new ApostropheTokenFilter(), ["it's", "doesn't", "j'aime", 'l’école'], ['it', 'doesn', 'j', 'l']];
        yield 'possessive' => [new EnglishPossessiveTokenFilter(), ["John's", 'James’s', 'its'], ['John', 'James', 'its']];
    }

    /**
     * @param list<string> $input
     * @param list<string> $expected
     */
    #[DataProvider('provider')]
    public function testFilter(TokenFilterInterface $filter, array $input, array $expected): void
    {
        self::assertSame($expected, $filter->filter($input));
    }

    public function testEmptyInput(): void
    {
        self::assertSame([], (new LowercaseTokenFilter())->filter([]));
    }

    public function testResultIsAlwaysAList(): void
    {
        self::assertSame(['a', 'b'], (new EmptyTokenFilter())->filter([3 => 'a', 7 => '', 9 => 'b']));
    }
}
