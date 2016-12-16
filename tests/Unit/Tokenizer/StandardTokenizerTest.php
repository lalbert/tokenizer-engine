<?php

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use TokenizerEngine\Tokenizer\WordTokenizer;
use TokenizerEngine\Tokenizer\StandardTokenizer;

class StandardTokenizerTest extends \PHPUnit_Framework_TestCase
{
    public function testTokenizer()
    {
        $string = 'The 2 QUICK Brown-Foxes jumped over the lazy dog\'s bone.';
        $tokens = (new StandardTokenizer())->tokenize($string);
        
        $this->assertEquals(['The', '2', 'QUICK', 'Brown', 'Foxes', 'jumped', 'over', 'the', 'lazy', 'dog\'s', 'bone.'], $tokens);
    }
}
