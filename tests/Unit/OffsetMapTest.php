<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\CharacterFilter\CharacterFilterInterface;
use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\CharacterFilter\MappingCharacterFilter;
use TokenizerEngine\CharacterFilter\PatternReplaceCharacterFilter;
use TokenizerEngine\OffsetMap;
use TokenizerEngine\Token;
use TokenizerEngine\Tokenizer\KeywordTokenizer;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\TokenizerEngine;

/**
 * Offsets are mapped back to the original text through character filters.
 * Expected values come from Elasticsearch 9.5 (_analyze API).
 */
final class OffsetMapTest extends TestCase
{
    private const HTML = '<p>Les élèves de l’<b>École</b> arrivent à Montréal.</p>';

    /**
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private static function spans(TokenizerEngine $engine, string $text): array
    {
        return array_map(static fn (Token $t): array => [$t->term, (int) $t->startOffset, (int) $t->endOffset], $engine->tokens($text));
    }

    private static function standard(CharacterFilterInterface ...$filters): TokenizerEngine
    {
        $engine = new TokenizerEngine();
        foreach ($filters as $filter) {
            $engine->addCharacterFilter($filter);
        }

        return $engine->addTokenizer(new StandardTokenizer());
    }

    public function testHtmlStrip(): void
    {
        self::assertSame(
            [['Les', 3, 6], ['élèves', 7, 13], ['de', 14, 16], ['l’École', 17, 31], ['arrivent', 32, 40], ['à', 41, 42], ['Montréal', 43, 51]],
            self::spans(self::standard(new HTMLStripCharacterFilter()), self::HTML),
        );
    }

    public function testHtmlEntitiesAndBlocks(): void
    {
        self::assertSame(
            [['Hello', 3, 8], ['world', 12, 21], ['café', 28, 39], ['Second', 46, 52], ['paragraph', 53, 62]],
            self::spans(self::standard(new HTMLStripCharacterFilter()), '<p>Hello <b>world</b> &amp; caf&eacute;</p><p>Second paragraph</p>'),
        );
    }

    public function testMapping(): void
    {
        self::assertSame(
            [['fone', 0, 5], ['yyya', 6, 8], ['ecole', 9, 14]],
            self::spans(self::standard(new MappingCharacterFilter(['ph => f', 'x => yyy', 'é => e'])), 'phone xa école'),
        );
    }

    public function testPatternReplace(): void
    {
        self::assertSame([['abcd', 0, 5], ['ef', 6, 8]], self::spans(self::standard(new PatternReplaceCharacterFilter('-', '')), 'ab-cd ef'));
        self::assertSame([['pi', 0, 2], ['3.14', 3, 7], ['ok', 8, 10]], self::spans(self::standard(new PatternReplaceCharacterFilter('(\d+),(\d+)', '$1.$2')), 'pi 3,14 ok'));
    }

    public function testChainedFilters(): void
    {
        $engine = self::standard(new HTMLStripCharacterFilter(), new MappingCharacterFilter(['é => e']));

        self::assertSame([['ecole', 3, 12], ['ete', 16, 37]], self::spans($engine, '<b>école</b> <i>&eacute;t&eacute;</i>'));
    }

    public function testKeywordSpansTheWholeSource(): void
    {
        $engine = (new TokenizerEngine())->addCharacterFilter(new HTMLStripCharacterFilter())->addTokenizer(new KeywordTokenizer());

        self::assertSame([['xyz', 0, 10]], self::spans($engine, 'x<b>y</b>z'));
    }

    public function testMarkupAndTextSegments(): void
    {
        [, $map] = (new TokenizerEngine())->addCharacterFilter(new HTMLStripCharacterFilter())->tokensAndOffsetMap(self::HTML);

        self::assertSame([[0, 3], [19, 22], [27, 31], [52, 56]], $map->markup());
        self::assertSame([[17, 19], [22, 27]], $map->textSegments(17, 31));
        self::assertSame([[43, 51]], $map->textSegments(43, 51));
    }

    public function testIdentity(): void
    {
        $map = OffsetMap::identity();

        self::assertTrue($map->isIdentity());
        self::assertSame(42, $map->toOriginal(42));
        self::assertSame([[2, 5]], $map->textSegments(2, 5));
    }

    public function testFiltersWithoutOffsetMappingAreKeptAsIs(): void
    {
        $filter = new class implements CharacterFilterInterface {
            public function filter(string $text): string
            {
                return strtoupper($text);
            }
        };

        self::assertSame([['AB', 0, 2]], self::spans(self::standard($filter), 'ab'));
    }
}
