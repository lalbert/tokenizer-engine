<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\MinHashTokenFilter;
use TokenizerEngine\TokenFilter\ShingleTokenFilter;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\TokenizerEngine;

final class MinHashTokenFilterTest extends TestCase
{
    private static function signature(string $text): array
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new ShingleTokenFilter(2, 2, false))
            ->addTokenFilter(new MinHashTokenFilter(256))
            ->tokenize($text);
    }

    public function testSignatureSize(): void
    {
        self::assertCount(16, (new MinHashTokenFilter(16))->filter(['a', 'b']));
        self::assertSame([], (new MinHashTokenFilter(16))->filter([]));
    }

    public function testIdenticalAndOrderIndependent(): void
    {
        $filter = new MinHashTokenFilter(32);

        self::assertSame($filter->filter(['a', 'b', 'c']), $filter->filter(['c', 'a', 'b', 'a']));
    }

    public function testSimilarity(): void
    {
        $a = self::signature('Le petit chat est assis sur le tapis rouge du salon ce matin');
        $b = self::signature('Le petit chat est assis sur le tapis bleu du salon ce matin');
        $c = self::signature('Les marchés financiers ont fortement baissé aujourd’hui en Europe');

        // Exact Jaccard similarity of a and b shingles: 10 / 14 = 0.71.
        self::assertEqualsWithDelta(0.71, MinHashTokenFilter::similarity($a, $b), 0.1);
        self::assertLessThan(0.1, MinHashTokenFilter::similarity($a, $c));
        self::assertSame(1.0, MinHashTokenFilter::similarity($a, $a));
    }

    public function testSimilarityNeedsSameSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MinHashTokenFilter::similarity(['0_a'], ['0_a', '1_b']);
    }
}
