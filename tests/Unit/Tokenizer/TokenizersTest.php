<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\Tokenizer\KeywordTokenizer;
use TokenizerEngine\Tokenizer\LetterTokenizer;
use TokenizerEngine\Tokenizer\PatternTokenizer;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\Tokenizer\WhitespaceTokenizer;

final class TokenizersTest extends TestCase
{
    private const SENTENCE = "The 2 QUICK Brown-Foxes jumped over the lazy dog's bone.";

    /**
     * @return iterable<string, array{bool}>
     */
    public static function intlModes(): iterable
    {
        yield 'intl' => [true];
        yield 'regex fallback' => [false];
    }

    /**
     * Same example as the Elasticsearch documentation of the standard tokenizer.
     */
    #[DataProvider('intlModes')]
    public function testStandard(bool $useIntl): void
    {
        self::assertSame(
            ['The', '2', 'QUICK', 'Brown', 'Foxes', 'jumped', 'over', 'the', 'lazy', "dog's", 'bone'],
            (new StandardTokenizer(255, $useIntl))->tokenize(self::SENTENCE),
        );
    }

    #[DataProvider('intlModes')]
    public function testStandardMaxTokenLength(bool $useIntl): void
    {
        self::assertSame(
            ['The', '2', 'QUICK', 'Brown', 'Foxes', 'jumpe', 'd', 'over', 'the', 'lazy', "dog's", 'bone'],
            (new StandardTokenizer(5, $useIntl))->tokenize(self::SENTENCE),
        );
    }

    #[DataProvider('intlModes')]
    public function testStandardUnicode(bool $useIntl): void
    {
        self::assertSame(
            ['L’école', 'à', '3,14', 'km', 'h', 'Ça', 'va'],
            (new StandardTokenizer(255, $useIntl))->tokenize('« L’école à 3,14 km/h ! » — Ça va ?'),
        );
    }

    public function testStandardTypes(): void
    {
        $types = array_map(static fn (Token $t): string => $t->type, (new StandardTokenizer())->tokenize([new Token('abc 42', 0, 0, 6)]));

        self::assertSame(['<ALPHANUM>', '<NUM>'], $types);
    }

    public function testWhitespace(): void
    {
        self::assertSame(
            ['The', '2', 'QUICK', 'Brown-Foxes', 'jumped', 'over', 'the', 'lazy', "dog's", 'bone.'],
            (new WhitespaceTokenizer())->tokenize(self::SENTENCE),
        );
        self::assertSame(['a', 'b'], (new WhitespaceTokenizer())->tokenize("a\u{00A0}\u{3000}b"));
    }

    public function testKeyword(): void
    {
        self::assertSame(['New York'], (new KeywordTokenizer())->tokenize('New York'));
        self::assertSame([], (new KeywordTokenizer())->tokenize(''));
    }

    public function testLetter(): void
    {
        self::assertSame(['The', 'QUICK', 'Brown', 'Foxes', 'dog', 's', 'été'], (new LetterTokenizer())->tokenize("The 2 QUICK Brown-Foxes dog's été"));
    }

    public function testPattern(): void
    {
        // Like Java, "_" is a word character for \W.
        self::assertSame(['The', 'foo_bar', 'école'], (new PatternTokenizer())->tokenize('The foo_bar, école!'));
        self::assertSame(['a', 'b c', 'd'], (new PatternTokenizer(','))->tokenize('a,b c,d'));
        self::assertSame(['value', 'value 2'], (new PatternTokenizer('"((?:\\\\"|[^"]|\\\\")+)"', '', 1))->tokenize('"value", "value 2"'));
    }

    public function testInvalidPattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PatternTokenizer('(');
    }

    public function testListOfStringsInput(): void
    {
        self::assertSame(['a', 'b', 'c'], (new WhitespaceTokenizer())->tokenize(['a b', 'c']));
    }

    public function testOffsetsAreInCharacters(): void
    {
        $tokens = (new StandardTokenizer())->tokenize([new Token('élan été', 0, 0, 8)]);

        self::assertSame([[0, 4, 0], [5, 8, 1]], array_map(static fn (Token $t): array => [$t->startOffset, $t->endOffset, $t->position], $tokens));
    }

    public function testOffsetsWithBaseOffset(): void
    {
        $tokens = (new WhitespaceTokenizer())->tokenize([new Token('ab cd', 7, 10, 15)]);

        self::assertSame([[10, 12], [13, 15]], array_map(static fn (Token $t): array => [$t->startOffset, $t->endOffset], $tokens));
    }

    public function testInvalidUtf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new StandardTokenizer())->tokenize("\xC3\x28");
    }
}
