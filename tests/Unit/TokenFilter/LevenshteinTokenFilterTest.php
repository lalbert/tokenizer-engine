<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\LevenshteinTokenFilter;

class LevenshteinTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ['carot', 'carott', 'pari', 'patis', 'equal'];
        $words = ['carrot', 'paris', 'equal'];

        $filter = new LevenshteinTokenFilter($words);

        $this->assertEquals(['carrot', 'carrot', 'paris', 'paris', 'equal'], array_values($filter->filter($tokens)));
    }

    public function testMaxDistance()
    {
        $tokens = ['carot', 'carott', 'pari', 'patis', 'equal'];
        $words = ['carrot', 'paris', 'equal'];

        $filter = new LevenshteinTokenFilter($words, 1);

        $this->assertEquals(['carrot', 'carott', 'paris', 'paris', 'equal'], array_values($filter->filter($tokens)));
    }
}
