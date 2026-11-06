<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Tokenizer;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\Tokenizer\CharGroupTokenizer;
use TokenizerEngine\Tokenizer\EdgeNGramTokenizer;
use TokenizerEngine\Tokenizer\NGramTokenizer;
use TokenizerEngine\Tokenizer\PathHierarchyTokenizer;
use TokenizerEngine\Tokenizer\UaxUrlEmailTokenizer;
use TokenizerEngine\TokenizerEngine;

final class NewTokenizersTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: string}> term, covered text, type
     */
    private static function spans(string $text, TokenizerEngine $engine): array
    {
        return array_map(
            static fn (Token $t): array => [$t->term, mb_substr($text, (int) $t->startOffset, (int) $t->endOffset - (int) $t->startOffset), $t->type],
            $engine->tokens($text),
        );
    }

    public function testUaxUrlEmail(): void
    {
        $text = 'Écrivez à luc@exemple.fr ou voyez https://exemple.fr/a?b=1. Merci';

        self::assertSame(
            [
                ['Écrivez', 'Écrivez', '<ALPHANUM>'],
                ['à', 'à', '<ALPHANUM>'],
                ['luc@exemple.fr', 'luc@exemple.fr', '<EMAIL>'],
                ['ou', 'ou', '<ALPHANUM>'],
                ['voyez', 'voyez', '<ALPHANUM>'],
                ['https://exemple.fr/a?b=1', 'https://exemple.fr/a?b=1', '<URL>'],
                ['Merci', 'Merci', '<ALPHANUM>'],
            ],
            self::spans($text, (new TokenizerEngine())->addTokenizer(new UaxUrlEmailTokenizer())),
        );
    }

    public function testUaxUrlEmailWww(): void
    {
        self::assertSame(['see', 'www.example.com', 'now'], (new UaxUrlEmailTokenizer())->tokenize('see www.example.com, now'));
    }

    public function testNGram(): void
    {
        self::assertSame(['Q', 'Qu', 'u', 'ui', 'i', 'ic', 'c', 'ck', 'k', 'k ', ' ', ' F', 'F', 'Fo', 'o', 'ox', 'x'], (new NGramTokenizer())->tokenize('Quick Fox'));
        self::assertSame(['qu', 'qui', 'ui', 'uic', 'ic', 'ick', 'ck', 'fo', 'fox', 'ox'], (new NGramTokenizer(2, 3, ['letter']))->tokenize('quick fox'));
    }

    public function testEdgeNGram(): void
    {
        self::assertSame(['Q', 'Qu'], (new EdgeNGramTokenizer())->tokenize('Quick Fox'));
        self::assertSame(['Qu', 'Qui', 'Fo', 'Fox', '2-', '2-q'], (new EdgeNGramTokenizer(2, 3, ['letter', 'digit', 'custom'], '-'))->tokenize('Quick Fox 2-quick'));
        self::assertSame(['él', 'éla'], (new EdgeNGramTokenizer(2, 3, ['letter']))->tokenize('élan'));
    }

    public function testInvalidTokenChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EdgeNGramTokenizer(1, 2, ['emoji']);
    }

    public function testPathHierarchy(): void
    {
        self::assertSame(['/one', '/one/two', '/one/two/three'], (new PathHierarchyTokenizer())->tokenize('/one/two/three'));
        self::assertSame(['/one/two/three', 'one/two/three', 'two/three', 'three'], (new PathHierarchyTokenizer(reverse: true))->tokenize('/one/two/three'));
        self::assertSame(['/two', '/two/three'], (new PathHierarchyTokenizer(skip: 1))->tokenize('/one/two/three'));
        self::assertSame(['a', 'a/b', 'a/b/c'], (new PathHierarchyTokenizer('-', '/'))->tokenize('a-b-c'));
        self::assertSame(['www.example.com', 'example.com', 'com'], (new PathHierarchyTokenizer('.', null, true))->tokenize('www.example.com'));
    }

    public function testPathHierarchyOffsets(): void
    {
        $spans = self::spans('/a/bé/c', (new TokenizerEngine())->addTokenizer(new PathHierarchyTokenizer()));

        self::assertSame(['/a', '/a/bé', '/a/bé/c'], array_column($spans, 1));
    }

    public function testCharGroup(): void
    {
        self::assertSame(['The', 'QUICK', 'brown', 'fox'], (new CharGroupTokenizer(['whitespace', '-', '\n']))->tokenize("The QUICK\nbrown-fox"));
        self::assertSame(['a', 'b'], (new CharGroupTokenizer(['punctuation']))->tokenize('a,b'));
    }
}
