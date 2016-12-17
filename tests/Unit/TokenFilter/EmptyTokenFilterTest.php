<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\EmptyTokenFilter;

class EmptyTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['a', '', null, '  ', 0, 'b'];
        $filter = new EmptyTokenFilter();

        $this->assertEquals([0 => 'a', '  ', 'b'], array_values($filter->filter($tokens)));
    }
}
