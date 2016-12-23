<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\FingerprintTokenFilter;

class FingerprintTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['the', 'quick', 'quick', 'brown', 'fox', 'was', 'very', 'brown'];
        $filter = new FingerprintTokenFilter();

        $this->assertEquals(['brown fox quick the very was'], $filter->filter($tokens));
    }

    public function testSeparator()
    {
        $tokens = ['the', 'quick', 'quick', 'brown', 'fox', 'was', 'very', 'brown'];
        $filter = new FingerprintTokenFilter('-');

        $this->assertEquals(['brown-fox-quick-the-very-was'], $filter->filter($tokens));
    }

    public function testMaxOutputSize()
    {
        $tokens = ['the', 'quick', 'quick', 'brown', 'fox', 'was', 'very', 'brown'];
        $filter = new FingerprintTokenFilter(' ', 5);

        $this->assertEquals([], $filter->filter($tokens));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Setting \[separator\] must be a single, non-null character\./
     */
    public function testBadSeparator()
    {
        $filter = new  FingerprintTokenFilter('--');
    }
}
