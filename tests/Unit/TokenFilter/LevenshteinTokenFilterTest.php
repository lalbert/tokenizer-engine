<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\LevenshteinTokenFilter;

final class LevenshteinTokenFilterTest extends TestCase
{
    private const TOKENS = ['carot', 'carott', 'pari', 'patis', 'equal'];
    private const WORDS = ['carrot', 'paris', 'equal'];

    public function testFilter(): void
    {
        self::assertSame(['carrot', 'carrot', 'paris', 'paris', 'equal'], (new LevenshteinTokenFilter(self::WORDS))->filter(self::TOKENS));
    }

    public function testMaxDistance(): void
    {
        self::assertSame(['carrot', 'carott', 'paris', 'paris', 'equal'], (new LevenshteinTokenFilter(self::WORDS, 1))->filter(self::TOKENS));
    }

    public function testDistanceIsComputedOnCharacters(): void
    {
        // "Bezier" vs "Bézier" is one edit, not two (bytes).
        self::assertSame(['Bézier'], (new LevenshteinTokenFilter(['Bézier'], 1))->filter(['Bezier']));
    }

    public function testFirstWordWinsOnTies(): void
    {
        self::assertSame(['cat'], (new LevenshteinTokenFilter(['cat', 'car']))->filter(['cax']));
    }
}
