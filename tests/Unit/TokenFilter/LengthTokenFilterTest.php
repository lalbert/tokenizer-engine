<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\LengthTokenFilter;

class LengthTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFiltre()
    {
        $tokens = [
            '1', '22', '333', '4444', '55555', '666666', '7777777', '88888888', '999999999',
        ];

        $filter = new LengthTokenFilter();
        $this->assertCount(9, $filter->filter($tokens));

        $filter = new LengthTokenFilter(0, 5);
        $this->assertCount(5, $filter->filter($tokens));

        $filter = new LengthTokenFilter(5, 7);
        $this->assertCount(3, $filter->filter($tokens));
    }
}
