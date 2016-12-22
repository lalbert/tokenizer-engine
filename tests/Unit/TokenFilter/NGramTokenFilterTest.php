<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\NGramTokenFilter;

class NGramTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['Hello', 'H', 'He', 'e', 'el', 'l', 'll', 'l', 'lo', 'o', 'world !', 'w', 'wo', 'o', 'or', 'r', 'rl', 'l', 'ld', 'd', 'd ', ' ', ' !', '!'];
        $filter = new NGramTokenFilter();

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    public function testPreserveOriginal()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['H', 'He', 'e', 'el', 'l', 'll', 'l', 'lo', 'o', 'w', 'wo', 'o', 'or', 'r', 'rl', 'l', 'ld', 'd', 'd ', ' ', ' !', '!'];
        $filter = new NGramTokenFilter(1, 2, false);

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    public function testMinMaxNgrams()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['Hello', 'He', 'Hel', 'Hell', 'el', 'ell', 'ello', 'll', 'llo', 'lo', 'world !', 'wo', 'wor', 'worl', 'or', 'orl', 'orld', 'rl', 'rld', 'rld ', 'ld', 'ld ', 'ld !', 'd ', 'd !', ' !'];
        $filter = new NGramTokenFilter(2, 4);

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage minGram and maxGram must be greater than 0.
     */
    public function testBadMinMaxValue()
    {
        $filter = new NGramTokenFilter(0, 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage maxGram must be greater than minGram.
     */
    public function testMinGreaterThanMax()
    {
        $filter = new NGramTokenFilter(2, 1);
    }
}
