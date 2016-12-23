<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\ReverseTokenFilter;

class ReverseTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['Hello', 'world'];
        $filter = new ReverseTokenFilter();

        $this->assertEquals(['olleH', 'dlrow'], $filter->filter($tokens));
    }
}
