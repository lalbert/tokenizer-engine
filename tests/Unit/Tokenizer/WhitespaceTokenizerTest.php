<?php

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use TokenizerEngine\Tokenizer\WhitespaceTokenizer;

class WhitespaceTokenizerTest extends \PHPUnit_Framework_TestCase
{
    public function testTokenizer()
    {
        $string = 'The 2 QUICK Brown-Foxes jumped over the lazy dog\'s bone.';
        $tokens = (new WhitespaceTokenizer())->tokenize($string);

        $this->assertEquals(['The', '2', 'QUICK', 'Brown-Foxes', 'jumped', 'over', 'the', 'lazy', 'dog\'s', 'bone.'], $tokens);
    }
}
