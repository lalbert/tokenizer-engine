<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\AlphaNumericTokenFilter;

class AlphaNumericTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['Foo', 'Bar', 'Foo-Bar', 'Foo|Bar'];
        $tokenFilter = new AlphaNumericTokenFilter();

        $this->assertEquals(['Foo', 'Bar', 'FooBar', 'FooBar'], $tokenFilter->filter($tokens));
    }
}
