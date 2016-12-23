<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\TruncateTokenFilter;

class TruncateTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['foo', 'averylongword', 'bar'];
        $filter = new TruncateTokenFilter();

        $this->assertEquals(['foo', 'averylongw', 'bar'], $filter->filter($tokens));
    }

    public function testLengthParameter()
    {
        $tokens = ['foo', 'averylongword', 'bar'];
        $filter = new TruncateTokenFilter(2);

        $this->assertEquals(['fo', 'av', 'ba'], $filter->filter($tokens));
    }
}
