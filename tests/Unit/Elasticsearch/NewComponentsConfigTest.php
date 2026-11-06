<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Elasticsearch\AnalysisConfig;

final class NewComponentsConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<string>
     */
    private static function analyze(array $analysis, string $text): array
    {
        return (new AnalysisConfig($analysis, null, true))->analyzer('a')->tokenize($text);
    }

    public function testFrenchAnalyzerWithStemExclusion(): void
    {
        self::assertSame(['chevaux', 'ecol'], self::analyze(['analyzer' => ['a' => ['type' => 'french', 'stem_exclusion' => ['chevaux']]]], 'Les chevaux et les écoles'));
    }

    public function testKeywordMarkerAndStemmerOverride(): void
    {
        $analysis = [
            'filter' => [
                'protect' => ['type' => 'keyword_marker', 'keywords' => ['paris']],
                'override' => ['type' => 'stemmer_override', 'rules' => ['courses => course']],
                'stem' => ['type' => 'stemmer', 'language' => 'light_french'],
            ],
            'analyzer' => ['a' => ['tokenizer' => 'standard', 'filter' => ['lowercase', 'protect', 'override', 'stem']]],
        ];

        self::assertSame(['paris', 'course', 'cheval'], self::analyze($analysis, 'Paris courses chevaux'));
    }

    public function testMultiplexer(): void
    {
        $analysis = [
            'filter' => ['mx' => ['type' => 'multiplexer', 'filters' => ['lowercase', 'lowercase, porter_stem']]],
            'analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => ['mx']]],
        ];

        self::assertSame(['Running', 'running', 'run'], self::analyze($analysis, 'Running'));
    }

    public function testTokenizers(): void
    {
        self::assertSame(['luc@ex.fr'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'uax_url_email', 'filter' => [['type' => 'keep_types', 'types' => ['<EMAIL>']]]]]], 'mail luc@ex.fr'));
        self::assertSame(['Qu', 'Qui'], self::analyze(['tokenizer' => ['t' => ['type' => 'edge_ngram', 'min_gram' => 2, 'max_gram' => 3, 'token_chars' => ['letter']]], 'analyzer' => ['a' => ['tokenizer' => 't']]], 'Quick'));
        self::assertSame(['a', 'a/b'], self::analyze(['tokenizer' => ['t' => ['type' => 'path_hierarchy', 'delimiter' => '-', 'replacement' => '/']], 'analyzer' => ['a' => ['tokenizer' => 't']]], 'a-b'));
        self::assertSame(['a', 'b'], self::analyze(['tokenizer' => ['t' => ['type' => 'char_group', 'tokenize_on_chars' => ['-']]], 'analyzer' => ['a' => ['tokenizer' => 't']]], 'a-b'));
    }

    public function testFilters(): void
    {
        self::assertSame(['a', 'a b', 'b'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => ['shingle']]]], 'a b'));
        self::assertSame(['LFVR'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'phonetic', 'encoder' => 'soundex2']]]]], 'Lefèvre'));
        self::assertSame(['12'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => ['decimal_digit']]]], '١٢'));
        self::assertSame(['dog'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'keep', 'keep_words' => ['dog']]]]]], 'the dog'));
        self::assertSame(['wtt'], self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'pattern_replace', 'pattern' => '[aeiou]', 'replacement' => '']]]]], 'watt'));
        self::assertCount(8, self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'min_hash', 'hash_count' => 8]]]]], 'a b c'));
    }

    public function testConditionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Painless/');
        self::analyze(['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'condition', 'script' => ['source' => 'true']]]]]], 'a');
    }
}
