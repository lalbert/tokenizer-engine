<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\LimitTokenCountTokenFilter;

class LimitTokenCountTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['foo', 'bar', 'baz'];
        $filter = new LimitTokenCountTokenFilter();

        $this->assertEquals(['foo'], $filter->filter($tokens));
    }

    public function testMaxTokenCount()
    {
        $tokens = ['foo', 'bar', 'baz'];
        $filter = new LimitTokenCountTokenFilter(2);

        $this->assertEquals(['foo', 'bar'], $filter->filter($tokens));
    }
}
