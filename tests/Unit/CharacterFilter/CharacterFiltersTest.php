<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\CharacterFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\CharacterFilter\MappingCharacterFilter;
use TokenizerEngine\CharacterFilter\PatternReplaceCharacterFilter;

final class CharacterFiltersTest extends TestCase
{
    private const HTML = '<body><h1>Title</h1> <p>Html body</p></body>';

    private static function words(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public function testHtmlStrip(): void
    {
        self::assertSame('Title Html body', self::words((new HTMLStripCharacterFilter())->filter(self::HTML)));
    }

    public function testHtmlStripKeepsWordsApart(): void
    {
        self::assertSame('one two', self::words((new HTMLStripCharacterFilter())->filter('<p>one</p><p>two</p>')));
        self::assertSame("a\nb", (new HTMLStripCharacterFilter())->filter('a<br/>b'));
    }

    public function testHtmlStripEscapedTags(): void
    {
        self::assertSame('Title <p>Html body</p>', self::words((new HTMLStripCharacterFilter('<p>'))->filter(self::HTML)));
        self::assertSame('Title <p>Html body</p>', self::words((new HTMLStripCharacterFilter(['p']))->filter(self::HTML)));
    }

    public function testHtmlStripEntitiesScriptsComments(): void
    {
        $html = '<p>I&apos;m so <b>happy</b>&nbsp;&amp; caf&eacute;!</p><script>alert(1)</script><style>p{}</style><!-- hidden -->';

        // Like Elasticsearch, &nbsp; becomes a regular space.
        self::assertSame("I'm so happy & café!", trim((new HTMLStripCharacterFilter())->filter($html)));
    }

    public function testMapping(): void
    {
        $filter = new MappingCharacterFilter(['٠ => 0', '١ => 1', ':) => _happy_', 'ph => f']);

        self::assertSame('My license plate is 10 _happy_ fone', $filter->filter('My license plate is ١٠ :) phone'));
        self::assertSame('xyz', (new MappingCharacterFilter(['a' => 'x', 'ab' => 'xy', 'c' => 'z']))->filter('abc'));
    }

    public function testInvalidMapping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MappingCharacterFilter(['no arrow']);
    }

    public function testPatternReplace(): void
    {
        $filter = new PatternReplaceCharacterFilter('(\d+)-(?=\d)', '$1_');

        self::assertSame('My credit card is 123_456_789', $filter->filter('My credit card is 123-456-789'));
        self::assertSame('x-é', (new PatternReplaceCharacterFilter('A', 'x', 'i'))->filter('a-é'));
    }
}
