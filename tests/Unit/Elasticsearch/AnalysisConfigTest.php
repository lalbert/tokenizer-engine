<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Analyzers;
use TokenizerEngine\Elasticsearch\AnalysisConfig;
use TokenizerEngine\Elasticsearch\ComponentRegistry;
use TokenizerEngine\Elasticsearch\Params;
use TokenizerEngine\TokenFilter\ReverseTokenFilter;
use TokenizerEngine\TokenizerEngine;

final class AnalysisConfigTest extends TestCase
{
    private static function config(bool $strict = false): AnalysisConfig
    {
        return AnalysisConfig::fromFile(__DIR__.'/../../Fixtures/elasticsearch-settings.json', null, $strict);
    }

    public function testAnalyzerNames(): void
    {
        self::assertSame(['rebuilt_french', 'autocomplete', 'csv', 'my_standard', 'my_french'], self::config()->analyzerNames());
    }

    public function testRebuiltFrenchMatchesBuiltinFrench(): void
    {
        $text = 'L’école des chevaux, c’était hier !';

        self::assertSame(Analyzers::french()->tokenize($text), self::config()->analyzer('rebuilt_french')->tokenize($text));
        self::assertSame(Analyzers::french()->tokenize($text), self::config()->analyzer('my_french')->tokenize($text));
    }

    public function testCustomAnalyzerWithCharFiltersAndInlineParams(): void
    {
        self::assertSame(
            ['el', 'ela', 'elan', '_h', '_ha', '_hap', '_happ', '_happy', '_happy_'],
            self::config()->analyzer('autocomplete')->tokenize('<b>Élan</b> :)'),
        );
    }

    public function testCustomTokenizer(): void
    {
        self::assertSame(['a', 'tgv', 'train', 'b c'], self::config()->analyzer('csv')->tokenize('a, tgv ,b c'));
    }

    public function testBuiltinAnalyzerWithParams(): void
    {
        self::assertSame(['quick', 'brown', 'foxes'], self::config()->analyzer('my_standard')->tokenize('The QUICK brownfoxes'));
    }

    public function testUndefinedNamesFallBackToBuiltins(): void
    {
        self::assertSame(['hello', 'world'], self::config()->analyzer('default')->tokenize('Hello World'));
        self::assertSame(['Hello World'], self::config()->analyzer('keyword')->tokenize('Hello World'));
    }

    public function testShortcut(): void
    {
        $engine = TokenizerEngine::fromElasticsearch('{"analysis": {"analyzer": {"default": {"tokenizer": "whitespace", "filter": ["uppercase"]}}}}');

        self::assertSame(['HELLO', 'WORLD'], $engine->tokenize('hello world'));
    }

    public function testAcceptedShapes(): void
    {
        $analysis = ['analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => 'reverse']]];

        foreach ([$analysis, ['analysis' => $analysis], ['index' => ['analysis' => $analysis]], ['settings' => ['index' => ['analysis' => $analysis]]]] as $settings) {
            self::assertSame(['cba'], (new AnalysisConfig($settings))->analyzer('a')->tokenize('abc'));
        }
    }

    public function testCustomRegistry(): void
    {
        $registry = ComponentRegistry::default()->addTokenFilter('my_reverse', static fn (Params $p) => new ReverseTokenFilter());
        $config = new AnalysisConfig(['analyzer' => ['a' => ['tokenizer' => 'keyword', 'filter' => ['my_reverse']]]], $registry);

        self::assertSame(['cba'], $config->analyzer('a')->tokenize('abc'));
        self::assertTrue($registry->hasTokenFilter('my_reverse'));
    }

    public function testUnknownFilter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown filter type "nope".');
        (new AnalysisConfig(['analyzer' => ['a' => ['tokenizer' => 'standard', 'filter' => ['nope']]]]))->analyzer('a');
    }

    public function testMissingTokenizer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AnalysisConfig(['analyzer' => ['a' => ['filter' => ['lowercase']]]]))->analyzer('a');
    }

    public function testInvalidParameterValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "min_gram" of filter "f" must be an integer.');
        (new AnalysisConfig(['filter' => ['f' => ['type' => 'ngram', 'min_gram' => 'x']], 'analyzer' => ['a' => ['tokenizer' => 'standard', 'filter' => ['f']]]]))->analyzer('a');
    }

    public function testStrictModeRejectsUnsupportedParameters(): void
    {
        $settings = ['filter' => ['wd' => ['type' => 'word_delimiter', 'type_table' => ['# => ALPHA']]], 'analyzer' => ['a' => ['tokenizer' => 'whitespace', 'filter' => ['wd']]]];

        self::assertSame(['a', 'b'], (new AnalysisConfig($settings))->analyzer('a')->tokenize('a-b'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported parameter(s): type_table.');
        (new AnalysisConfig($settings, null, true))->analyzer('a');
    }

    public function testStrictModeAcceptsTheFixture(): void
    {
        foreach (self::config(true)->analyzerNames() as $name) {
            self::assertNotSame([], self::config(true)->analyzer($name)->tokenize('Hello, world'), $name);
        }
    }

    public function testInvalidJson(): void
    {
        $this->expectException(\JsonException::class);
        AnalysisConfig::fromJson('{');
    }
}
