<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\ElisionTokenFilter;

class ElisionTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = ["l'arbre", "qu'un"];
        $filter = new ElisionTokenFilter(['l', 'qu']);

        $this->assertEquals(['arbre', 'un'], $filter->filter($tokens));
    }
}
