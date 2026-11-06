<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\SynonymTokenFilter;

final class SynonymTokenFilterTest extends TestCase
{
    public function testEquivalentSynonyms(): void
    {
        $filter = new SynonymTokenFilter(['ipod, i-pod, i pod']);

        self::assertSame(['my', 'ipod', 'i-pod', 'i', 'pod'], $filter->filter(['my', 'ipod']));
        self::assertSame(['my', 'i', 'pod', 'ipod', 'i-pod'], $filter->filter(['my', 'i', 'pod']));
    }

    public function testNoExpand(): void
    {
        self::assertSame(['ipod', 'ipod'], (new SynonymTokenFilter(['ipod, i-pod'], expand: false))->filter(['i-pod', 'ipod']));
    }

    public function testExplicitMapping(): void
    {
        $filter = new SynonymTokenFilter(['universe, cosmos => cosmos', 'sea biscuit, sea biscit => seabiscuit']);

        self::assertSame(['cosmos', 'cosmos', 'seabiscuit', 'horse'], $filter->filter(['universe', 'cosmos', 'sea', 'biscit', 'horse']));
    }

    public function testAssociativeArray(): void
    {
        self::assertSame(['tv', 'television'], (new SynonymTokenFilter(['tv' => ['tv', 'television']]))->filter(['tv']));
    }

    public function testRulesAreMerged(): void
    {
        $filter = new SynonymTokenFilter(['a => b', 'a => c']);

        self::assertSame(['b', 'c'], $filter->filter(['a']));
    }

    public function testFile(): void
    {
        $filter = new SynonymTokenFilter([], __DIR__.'/../../Fixtures/synonyms.txt');

        self::assertSame(['voiture', 'automobile', 'auto'], $filter->filter(['voiture']));
        self::assertSame(['tgv', 'train', 'à', 'grande', 'vitesse'], $filter->filter(['tgv']));
    }

    public function testIgnoreCase(): void
    {
        self::assertSame(['TV', 'television'], (new SynonymTokenFilter(['tv, television'], ignoreCase: true))->filter(['TV']));
    }

    public function testPositionsAndOffsets(): void
    {
        $filter = new SynonymTokenFilter(['ny => new york']);
        $result = $filter->filter([new Token('ny', 0, 0, 2), new Token('city', 1, 3, 7)]);

        self::assertSame(
            [['new', 0, 0, 2, 'SYNONYM'], ['york', 1, 0, 2, 'SYNONYM'], ['city', 1, 3, 7, 'word']],
            array_map(static fn (Token $t): array => [$t->term, $t->position, $t->startOffset, $t->endOffset, $t->type], $result),
        );
    }

    public function testInvalidRule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SynonymTokenFilter(['a => b => c']);
    }
}
