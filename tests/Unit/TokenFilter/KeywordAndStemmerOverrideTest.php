<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Analyzers;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\KeywordMarkerTokenFilter;
use TokenizerEngine\TokenFilter\StemmerOverrideTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;

final class KeywordAndStemmerOverrideTest extends TestCase
{
    /**
     * @param list<string> $terms
     *
     * @return list<string>
     */
    private static function stem(KeywordMarkerTokenFilter|StemmerOverrideTokenFilter $marker, array $terms): array
    {
        $tokens = $marker->filter(array_map(static fn (string $t): Token => new Token($t), $terms));

        return array_map(static fn (Token $t): string => $t->term, (new StemmerTokenFilter('light_french'))->filter($tokens));
    }

    public function testKeywordsAreNotStemmed(): void
    {
        self::assertSame(['chevaux', 'ecol'], self::stem(new KeywordMarkerTokenFilter(['chevaux']), ['chevaux', 'école']));
        self::assertSame(['paris', 'cheval'], self::stem(new KeywordMarkerTokenFilter(['paris']), ['paris', 'chevaux']));
    }

    public function testPatternAndIgnoreCase(): void
    {
        self::assertSame(['Chevaux', 'iphones'], self::stem(new KeywordMarkerTokenFilter(['chevaux'], ignoreCase: true, keywordsPattern: 'i[a-z]+'), ['Chevaux', 'iphones']));
    }

    public function testFlag(): void
    {
        $tokens = (new KeywordMarkerTokenFilter(['a']))->filter([new Token('a'), new Token('b')]);

        self::assertSame([true, false], array_map(static fn (Token $t): bool => $t->keyword, $tokens));
    }

    public function testStemmerOverride(): void
    {
        self::assertSame(['course', 'course', 'cheval'], self::stem(new StemmerOverrideTokenFilter(['courses, courir => course']), ['courses', 'courir', 'chevaux']));
        self::assertSame(['x'], (new StemmerOverrideTokenFilter(['a' => 'x']))->filter(['a']));
    }

    public function testInvalidOverride(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StemmerOverrideTokenFilter(['no arrow']);
    }

    public function testStemExclusionInAnalyzers(): void
    {
        self::assertSame(['chevaux', 'ecol'], Analyzers::french(stemExclusion: ['chevaux'])->tokenize('Les chevaux et les écoles'));
    }

    public function testFrenchStemmerNames(): void
    {
        self::assertSame(['ecol'], (new StemmerTokenFilter('light_french'))->filter(['école']));
        self::assertSame(['cheval'], (new StemmerTokenFilter('minimal_french'))->filter(['chevaux']));
    }
}
