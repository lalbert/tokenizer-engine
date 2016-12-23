<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\EdgeNGramTokenFilter;

class EdgeNGramTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['Hello', 'H', 'He', 'world !', 'w', 'wo'];
        $filter = new EdgeNGramTokenFilter();

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    public function testPreserveOriginal()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['H', 'He', 'w', 'wo'];
        $filter = new EdgeNGramTokenFilter(1, 2, false);

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    public function testMinMaxNgrams()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['Hello', 'He', 'Hel', 'Hell', 'world !', 'wo', 'wor', 'worl'];
        $filter = new EdgeNGramTokenFilter(2, 4);

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    public function testSameMinMaxValue()
    {
        $tokens = ['Hello', 'world !'];
        $expected = ['Hello', 'Hel', 'world !', 'wor'];
        $filter = new EdgeNGramTokenFilter(3, 3);

        $this->assertEquals($expected, array_values($filter->filter($tokens)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage minGram and maxGram must be greater than 0.
     */
    public function testBadMinMaxValue()
    {
        $filter = new EdgeNGramTokenFilter(0, 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage maxGram must be greater than minGram.
     */
    public function testMinGreaterThanMax()
    {
        $filter = new EdgeNGramTokenFilter(2, 1);
    }
}
