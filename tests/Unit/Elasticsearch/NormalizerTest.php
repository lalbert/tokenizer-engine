<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Elasticsearch\AnalysisConfig;
use TokenizerEngine\Elasticsearch\ComponentRegistry;
use TokenizerEngine\TokenFilter\ReverseTokenFilter;

final class NormalizerTest extends TestCase
{
    private static function config(): AnalysisConfig
    {
        return new AnalysisConfig(['settings' => ['analysis' => [
            'char_filter' => ['no_dashes' => ['type' => 'mapping', 'mappings' => ['- => ']]],
            'normalizer' => [
                'folding' => ['type' => 'custom', 'filter' => ['trim', 'lowercase', 'asciifolding']],
                'sku' => ['type' => 'custom', 'char_filter' => ['no_dashes'], 'filter' => ['uppercase']],
            ],
        ]]], null, true);
    }

    public function testNormalize(): void
    {
        self::assertSame('cafe de paris', self::config()->normalizer('folding')->normalize('  Café De PARIS '));
        self::assertSame('AB12', self::config()->normalizer('sku')->normalize('ab-12'));
        self::assertSame('', self::config()->normalizer('folding')->normalize(''));
    }

    public function testValueIsNotSplit(): void
    {
        self::assertSame(['new york city'], self::config()->normalizer('folding')->engine()->tokenize('New York City'));
    }

    public function testBuiltinLowercase(): void
    {
        self::assertSame('école', (new AnalysisConfig([]))->normalizer('lowercase')->normalize('ÉCOLE'));
    }

    public function testNames(): void
    {
        self::assertSame(['folding', 'sku'], self::config()->normalizerNames());
    }

    public function testUnknownNormalizer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::config()->normalizer('nope');
    }

    public function testOnlyCharacterLevelComponentsAreAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"porter_stem" cannot be used in normalizer "n"/');
        (new AnalysisConfig(['normalizer' => ['n' => ['type' => 'custom', 'filter' => ['porter_stem']]]]))->normalizer('n');
    }

    public function testCustomComponentsCanBeAllowed(): void
    {
        $registry = ComponentRegistry::default()->addTokenFilter('my_reverse', static fn () => new ReverseTokenFilter());
        $config = (new AnalysisConfig(['normalizer' => ['n' => ['type' => 'custom', 'filter' => ['my_reverse']]]], $registry))->allowInNormalizers('my_reverse');

        self::assertSame('cba', $config->normalizer('n')->normalize('abc'));
    }
}
