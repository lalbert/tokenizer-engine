<?php
namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\TrimTokenFilter;

class TrimTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['  a  ', 'b  ', '  c'];
        $filter = new TrimTokenFilter();
        
        $this->assertEquals(['a', 'b', 'c'], array_values($filter->filter($tokens)));
    }
}