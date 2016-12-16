<?php

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use TokenizerEngine\Tokenizer\StandardTokenizer;

class StandardTokenizerTest extends \PHPUnit_Framework_TestCase
{
    public function testTokenizer()
    {
        $string = 'The 2 QUICK Brown-Foxes jumped over the lazy dog\'s bone.';
        $tokens = (new StandardTokenizer())->tokenize($string);

        $this->assertEquals(['The', '2', 'QUICK', 'Brown', 'Foxes', 'jumped', 'over', 'the', 'lazy', 'dog\'s', 'bone.'], $tokens);
    }

    public function testMaxTokenLength()
    {
        $string = 'The 2 QUICK Brown-Foxes jumped over the lazy dog\'s bone.';
        $tokens = (new StandardTokenizer(5))->tokenize($string);

        $this->assertEquals(['The', '2', 'QUICK', 'Brown', 'Foxes', 'jumpe', 'd', 'over', 'the', 'lazy', 'dog\'s', 'bone.'], $tokens);
    }
}
