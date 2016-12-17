<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\UniqueTokenFilter;

class UniqueTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['foo', 'bar', 'foo', 'bar', 'baz'];
        $filter = new UniqueTokenFilter();

        $this->assertEquals(['foo', 'bar', 'baz'], array_values($filter->filter($tokens)));
    }
}
