<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Elasticsearch\AnalysisConfig;
use TokenizerEngine\Hyphenation\HyphenationTree;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\DictionaryDecompounderTokenFilter;
use TokenizerEngine\TokenFilter\HyphenationDecompounderTokenFilter;

/**
 * Expected values come from Elasticsearch 9.5.
 */
final class DecompounderTokenFiltersTest extends TestCase
{
    private const WORDS = ['donau', 'dampf', 'schiff', 'dampfschiff', 'haupt', 'bahn', 'hof', 'bahnhof', 'rat', 'haus', 'rathaus', 'platz', 'kaffee', 'tasse'];
    private const PATTERNS = __DIR__.'/../../Parity/hyphenation-test.xml';

    /**
     * @param list<string> $words
     *
     * @return list<string>
     */
    private static function decompound(DictionaryDecompounderTokenFilter|HyphenationDecompounderTokenFilter $filter, array $words): array
    {
        return array_map(static fn (Token $t): string => $t->term, $filter->filter(array_map(static fn (string $w, int $i): Token => new Token($w, $i, 0, 1), $words, array_keys($words))));
    }

    public function testDictionaryBruteForce(): void
    {
        self::assertSame(
            ['donaudampfschiff', 'donau', 'dampf', 'dampfschiff', 'schiff'],
            self::decompound(new DictionaryDecompounderTokenFilter(self::WORDS), ['donaudampfschiff']),
        );
    }

    public function testDictionaryLongestMatchLikeElasticsearch(): void
    {
        self::assertSame(
            ['donaudampfschiff', 'donau', 'dampfschiff', 'hauptbahnhof', 'haupt', 'bahnhof', 'haus'],
            self::decompound(new DictionaryDecompounderTokenFilter(self::WORDS, onlyLongestMatch: true), ['donaudampfschiff', 'hauptbahnhof', 'haus']),
        );
    }

    public function testSubwordsKeepPositionAndOffsets(): void
    {
        $tokens = (new DictionaryDecompounderTokenFilter(self::WORDS))->filter([new Token('kaffeetasse', 3, 10, 21)]);

        self::assertSame(
            [['kaffeetasse', 3, 10, 21, 'word'], ['kaffee', 3, 10, 21, 'subword'], ['tasse', 3, 10, 21, 'subword']],
            array_map(static fn (Token $t): array => [$t->term, $t->position, $t->startOffset, $t->endOffset, $t->type], $tokens),
        );
    }

    public function testHyphenationTree(): void
    {
        $tree = HyphenationTree::fromFile(self::PATTERNS);

        self::assertSame([0, 2, 5, 7, 14, 16], $tree->hyphenate('donaudampfschiff'));
        self::assertSame([0, 3, 6, 9, 11], $tree->hyphenate('kaffeetasse'), 'exception');
        self::assertSame([0, 2, 5, 7, 14, 16], $tree->hyphenate('Donaudampfschiff'), 'classes map case');
        self::assertNull($tree->hyphenate('a'));
        self::assertNull($tree->hyphenate('don4au'), 'letter after a non-letter');
    }

    public function testHyphenationDecompounder(): void
    {
        $tree = HyphenationTree::fromFile(self::PATTERNS);
        $words = ['hauptbahnhof', 'rathausplatz'];

        self::assertSame(
            ['hauptbahnhof', 'haupt', 'bahnhof', 'bahn', 'hof', 'rathausplatz', 'rathaus', 'rathaus', 'rat', 'haus', 'haus', 'platz'],
            self::decompound(new HyphenationDecompounderTokenFilter($tree, self::WORDS), $words),
        );
        self::assertSame(
            ['hauptbahnhof', 'haupt', 'bahnhof', 'hof', 'rathausplatz', 'rathaus', 'haus', 'platz'],
            self::decompound(new HyphenationDecompounderTokenFilter($tree, self::WORDS, onlyLongestMatch: true), $words),
        );
        self::assertSame(
            ['hauptbahnhof', 'haupt', 'bahnhof', 'rathausplatz', 'rathaus', 'platz'],
            self::decompound(new HyphenationDecompounderTokenFilter($tree, self::WORDS, noSubMatches: true), $words),
        );
        self::assertSame(
            ['hauptbahnhof', 'haupt', 'bahnhof', 'rathausplatz', 'rathaus'],
            self::decompound(new HyphenationDecompounderTokenFilter($tree, self::WORDS, noOverlappingMatches: true), $words),
        );
    }

    public function testDictionaryWordsAreNotDecomposedWithoutSubMatches(): void
    {
        $tree = HyphenationTree::fromFile(self::PATTERNS);

        self::assertSame(['bahnhof'], self::decompound(new HyphenationDecompounderTokenFilter($tree, self::WORDS, onlyLongestMatch: true), ['bahnhof']));
    }

    public function testFromElasticsearchSettings(): void
    {
        $config = new AnalysisConfig(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [
            'lowercase',
            ['type' => 'hyphenation_decompounder', 'hyphenation_patterns_path' => self::PATTERNS, 'word_list' => self::WORDS, 'no_sub_matches' => true],
        ]]]], null, true);

        self::assertSame(['hauptbahnhof', 'haupt', 'bahnhof'], $config->analyzer('a')->tokenize('Hauptbahnhof'));
    }

    public function testInvalidPatterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HyphenationTree::fromXml('<not-xml');
    }
}
