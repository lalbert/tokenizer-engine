<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\WordDelimiterTokenFilter;

final class WordDelimiterTokenFilterTest extends TestCase
{
    /**
     * @return iterable<array{string, list<string>}>
     */
    public static function defaults(): iterable
    {
        yield ['Wi-Fi', ['Wi', 'Fi']];
        yield ['PowerShot', ['Power', 'Shot']];
        yield ['SD500', ['SD', '500']];
        yield ['//hello---there, dude', ['hello', 'there', 'dude']];
        yield ["O'Neil's", ['O', 'Neil']];
        yield ['j2se', ['j', '2', 'se']];
        yield ['ÉcoleNormale', ['École', 'Normale']];
        yield ['Ça-va', ['Ça', 'va']];
        yield ['---', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('defaults')]
    public function testDefaults(string $token, array $expected): void
    {
        self::assertSame($expected, (new WordDelimiterTokenFilter())->filter([$token]));
    }

    public function testCatenateWords(): void
    {
        $filter = new WordDelimiterTokenFilter(['catenateWords' => true]);

        self::assertSame(['wi', 'fi', 'wifi'], $filter->filter(['wi-fi']));
        self::assertSame(['hello'], $filter->filter(['hello']));
    }

    public function testCatenateNumbers(): void
    {
        self::assertSame(['500', '42', '50042'], (new WordDelimiterTokenFilter(['catenateNumbers' => true]))->filter(['500-42']));
    }

    public function testCatenateAll(): void
    {
        self::assertSame(['wifi4000', 'wi', 'fi', '4000'], (new WordDelimiterTokenFilter(['catenateAll' => true]))->filter(['wi-fi-4000']));
    }

    public function testPreserveOriginal(): void
    {
        $filter = new WordDelimiterTokenFilter(['preserveOriginal' => true]);

        self::assertSame(['Wi-Fi', 'Wi', 'Fi'], $filter->filter(['Wi-Fi']));
        self::assertSame(['hello'], $filter->filter(['hello']));
    }

    public function testProtectedWords(): void
    {
        self::assertSame(['Wi-Fi', 'Power', 'Shot'], (new WordDelimiterTokenFilter(['protectedWords' => ['Wi-Fi']]))->filter(['Wi-Fi', 'PowerShot']));
    }

    public function testOptionsOff(): void
    {
        $filter = new WordDelimiterTokenFilter(['splitOnCaseChange' => false, 'splitOnNumerics' => false, 'stemEnglishPossessive' => false]);

        self::assertSame(['PowerShot', 'SD500', 'O', 'Neil', 's'], $filter->filter(['PowerShot', 'SD500', "O'Neil's"]));
    }

    public function testNoWordParts(): void
    {
        self::assertSame(['4000'], (new WordDelimiterTokenFilter(['generateWordParts' => false]))->filter(['wi-fi-4000']));
    }

    public function testInvalidParameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordDelimiterTokenFilter(['foo' => true]);
    }

    public function testOffsets(): void
    {
        $result = (new WordDelimiterTokenFilter())->filter([new Token('Élan-vital', 3, 10, 20)]);

        self::assertSame(
            [['Élan', 10, 14, 3], ['vital', 15, 20, 3]],
            array_map(static fn (Token $t): array => [$t->term, $t->startOffset, $t->endOffset, $t->position], $result),
        );
    }
}
