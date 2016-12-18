<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\StopTokenFilter;

class StopTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * English default stop words.
     */
    public function testDefault()
    {
        $tokens = ['foo', 'and', 'et', 'bar'];
        $filter = new StopTokenFilter();

        $this->assertEquals(['foo', 'et', 'bar'], array_values($filter->filter($tokens)));
    }

    public function testFr()
    {
        $tokens = ['foo', 'and', 'et', 'bar'];
        $filter = new StopTokenFilter('_french_');

        $this->assertEquals(['foo', 'and', 'bar'], array_values($filter->filter($tokens)));
    }

    public function testNoneStopWords()
    {
        $tokens = ['foo', 'and', 'et', 'bar'];
        $filter = new StopTokenFilter('_none_');

        $this->assertEquals(['foo', 'and', 'et', 'bar'], array_values($filter->filter($tokens)));
    }

    public function testCustomStopWords()
    {
        $tokens = ['foo', 'and', 'et', 'bar'];
        $filter = new StopTokenFilter(['foo', 'bar']);

        $this->assertEquals(['and', 'et'], array_values($filter->filter($tokens)));
    }

    public function testCustomStopWordsFile()
    {
        $tokens = ['foo', 'and', 'et', 'bar'];
        $filename = __DIR__.'/../../Fixtures/customStopWords.txt';
        $filter = new StopTokenFilter(null, $filename);

        $this->assertEquals(['and', 'et'], array_values($filter->filter($tokens)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Filename ".*" does not exists.$/
     */
    public function testCustomStopWordFileNotExists()
    {
        $filename = __DIR__.'/../../Fixtures/notExists.txt';
        new StopTokenFilter(null, $filename);
    }

    public function testIgnoreCase()
    {
        $tokens = ['foo', 'and', 'et', 'bar', 'AND'];

        $filter = new StopTokenFilter();
        $this->assertEquals(['foo', 'et', 'bar', 'AND'], array_values($filter->filter($tokens)));

        $filter = new StopTokenFilter('_english_', null, true);
        $this->assertEquals(['foo', 'et', 'bar'], array_values($filter->filter($tokens)));
    }
}
