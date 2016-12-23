<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\ApostropheTokenFilter;

class ApostropheTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ["it's", "doesn't", "j'aime"];
        $filter = new ApostropheTokenFilter();

        $this->assertEquals(['it', 'doesn', 'j'], $filter->filter($tokens));
    }
}
