<?php

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use TokenizerEngine\Tokenizer\WordTokenizer;

class WordTokenizerTest extends \PHPUnit_Framework_TestCase
{
    public function testTokenizer()
    {
        $string = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
        $tokens = (new WordTokenizer())->tokenize($string);

        $this->assertEquals(['Lorem', 'ipsum', 'dolor', 'sit', 'amet,', 'consectetur', 'adipiscing', 'elit.'], $tokens);
    }
}
