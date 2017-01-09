<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\WordDelimiterTokenFilter;

class WordDelimiterTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider standarsFilterProvider
     *
     * @param array $tokens
     * @param array $expected
     */
    public function testStandardFilter($tokens, $expected)
    {
        $filter = new WordDelimiterTokenFilter();
        $this->assertEquals($expected, $filter->filter($tokens));
    }

    public function standarsFilterProvider()
    {
        return [
            [['Wi-Fi'], ['Wi', 'Fi']],
            [['PowerShot'], ['Power', 'Shot']],
            [['SD500'], ['SD', '500']],
            [['//hello---there, dude'], ['hello', 'there', 'dude']],
            [["O'Neil's"], ['O', 'Neil']],
            [['j2se'], ['j', '2', 'se']],
        ];
    }

    public function testCatenateWords()
    {
        $filter = new WordDelimiterTokenFilter(['catenateWords' => true]);
        $this->assertEquals(['wi', 'fi', 'wifi'], $filter->filter(['wi-fi']));
    }

    public function testCatenateNumbers()
    {
        $filter = new WordDelimiterTokenFilter(['catenateNumbers' => true]);
        $this->assertEquals(['500', '42', '50042'], $filter->filter(['500-42']));
    }

    public function testCatenateAll()
    {
        $filter = new WordDelimiterTokenFilter(['catenateAll' => true]);
        $this->assertEquals(['wifi4000', 'wi', 'fi', '4000'], $filter->filter(['wi-fi-4000']));
    }
}
