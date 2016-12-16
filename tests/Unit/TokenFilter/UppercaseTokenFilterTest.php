<?php
namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\UppercaseTokenFilter;

class UppercaseTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['AAA', 'AaA', 'aAa'];
        $filter = new UppercaseTokenFilter();
        
        $this->assertEquals(['AAA', 'AAA', 'AAA'], array_values($filter->filter($tokens)));
    }
}