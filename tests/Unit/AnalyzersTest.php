<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Analyzers;

final class AnalyzersTest extends TestCase
{
    public function testStandard(): void
    {
        self::assertSame(
            ['the', '2', 'quick', 'brown', 'foxes', 'jumped', 'over', 'the', 'lazy', "dog's", 'bone'],
            Analyzers::standard()->tokenize("The 2 QUICK Brown-Foxes jumped over the lazy dog's bone."),
        );
        self::assertSame(['quick', 'fox'], Analyzers::standard('_english_')->tokenize('The quick fox'));
    }

    public function testSimpleWhitespaceKeywordStop(): void
    {
        self::assertSame(['the', 'quick', 'brown', 'foxes'], Analyzers::simple()->tokenize('The 2 QUICK Brown-Foxes'));
        self::assertSame(['The', 'QUICK', 'Brown-Foxes'], Analyzers::whitespace()->tokenize('The QUICK Brown-Foxes'));
        self::assertSame(['The QUICK'], Analyzers::keyword()->tokenize('The QUICK'));
        self::assertSame(['quick', 'brown', 'foxes'], Analyzers::stop()->tokenize('The QUICK Brown-Foxes'));
    }

    public function testFingerprint(): void
    {
        self::assertSame(Analyzers::fingerprint()->tokenize('Café de Paris'), Analyzers::fingerprint()->tokenize('paris, CAFE de, café'));
        self::assertSame(['cafe de paris'], Analyzers::fingerprint()->tokenize('Café de Paris'));
    }

    public function testFrench(): void
    {
        self::assertSame(['écol', 'cheval', 'hi'], Analyzers::french()->tokenize('L’école des chevaux, c’était hier !'));
    }

    public function testEnglish(): void
    {
        self::assertSame(['quick', 'brown', 'fox', 'jump', 'over', 'john', 'lazi', 'dog'], Analyzers::english()->tokenize("The QUICK brown foxes jumped over John's lazy dogs"));
    }

    public function testOtherLanguages(): void
    {
        self::assertSame(['katz', 'haus'], Analyzers::german()->tokenize('Die Katzen und die Häuser'));
        self::assertSame(['gat', 'cas'], Analyzers::spanish()->tokenize('Los gatos y las casas'));
        self::assertSame(['gatt', 'amic'], Analyzers::italian()->tokenize("Il gatto dell'amico"));
        self::assertSame(['gat', 'cas'], Analyzers::portuguese()->tokenize('Os gatos e as casas'));
        self::assertSame(['kat', 'huiz'], Analyzers::dutch()->tokenize('De katten en de huizen'));
    }

    public function testGet(): void
    {
        foreach (['standard', 'default', 'simple', 'whitespace', 'keyword', 'stop', 'fingerprint', ...Analyzers::LANGUAGES] as $name) {
            self::assertNotSame([], Analyzers::get($name)->tokenize('Hello world'), $name);
        }

        $this->expectException(\InvalidArgumentException::class);
        Analyzers::get('klingon');
    }
}
