<?php

namespace TokenizerEngine\Tests\Unit\CharacterFilter;

use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;

class HTMLStripCharacterFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $string = '<body><h1>Title</h1> <p>Html body</p></body>';
        $HTMLStripCharacterFilter = new HTMLStripCharacterFilter();

        $this->assertEquals('Title Html body', $HTMLStripCharacterFilter->filter($string));
    }

    public function testAllowedTags()
    {
        $string = '<body><h1>Title</h1> <p>Html body</p></body>';
        $HTMLStripCharacterFilter = new HTMLStripCharacterFilter('<p>');

        $this->assertEquals('Title <p>Html body</p>', $HTMLStripCharacterFilter->filter($string));
    }
}
