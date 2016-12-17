<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\LowercaseTokenFilter;

class LowercaseTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['AAA', 'AaA', 'aAa'];
        $filter = new LowercaseTokenFilter();

        $this->assertEquals(['aaa', 'aaa', 'aaa'], array_values($filter->filter($tokens)));
    }
}
