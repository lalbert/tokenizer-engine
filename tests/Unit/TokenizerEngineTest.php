<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\AsciiFoldingTokenFilter;
use TokenizerEngine\TokenFilter\ElisionTokenFilter;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\StopTokenFilter;
use TokenizerEngine\TokenFilter\TokenFilterInterface;
use TokenizerEngine\TokenFilter\WordDelimiterTokenFilter;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\Tokenizer\TokenizerInterface;
use TokenizerEngine\Tokenizer\WhitespaceTokenizer;
use TokenizerEngine\TokenizerEngine;

final class TokenizerEngineTest extends TestCase
{
    private function engine(): TokenizerEngine
    {
        return new TokenizerEngine([
            'characterFilters' => [new HTMLStripCharacterFilter()],
            'tokenizers' => [new StandardTokenizer()],
            'tokenFilters' => [new LowercaseTokenFilter(), new ElisionTokenFilter(), new StopTokenFilter('_french_'), new AsciiFoldingTokenFilter()],
        ]);
    }

    public function testTokenize(): void
    {
        self::assertSame(['ecole', 'ete', 'arrives', 'paris'], $this->engine()->tokenize('<p>L’École et l’été</p><p>sont arrivés à Paris</p>'));
    }

    public function testFluentApi(): void
    {
        $engine = (new TokenizerEngine())
            ->addTokenizer(new WhitespaceTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter());

        self::assertSame(['hello', 'world!'], $engine->tokenize('Hello World!'));
        self::assertCount(1, $engine->getTokenizers());
        self::assertCount(1, $engine->getTokenFilters());
        self::assertCount(0, $engine->getCharacterFilters());
    }

    public function testSingleComponentsInConfig(): void
    {
        $engine = new TokenizerEngine(['tokenizers' => new WhitespaceTokenizer()]);

        self::assertSame(['a', 'b'], $engine->tokenize('a b'));
    }

    public function testWithoutTokenizerTheTextIsOneToken(): void
    {
        $engine = new TokenizerEngine(['tokenFilters' => [new LowercaseTokenFilter()]]);

        self::assertSame(['hello world'], $engine->tokenize('Hello World'));
        self::assertSame([], $engine->tokenize(''));
    }

    public function testChainedTokenizers(): void
    {
        $engine = (new TokenizerEngine())
            ->addTokenizer(new WhitespaceTokenizer())
            ->addTokenizer(new StandardTokenizer());

        $tokens = $engine->tokens('foo-bar baz');
        self::assertSame(
            [['foo', 0, 3, 0], ['bar', 4, 7, 1], ['baz', 8, 11, 2]],
            array_map(static fn (Token $t): array => [$t->term, $t->startOffset, $t->endOffset, $t->position], $tokens),
        );
    }

    public function testTokensKeepPositionsAndOffsets(): void
    {
        $tokens = $this->engine()->tokens('Le café de Paris');

        self::assertSame(
            [
                ['token' => 'cafe', 'start_offset' => 3, 'end_offset' => 7, 'type' => '<ALPHANUM>', 'position' => 1],
                ['token' => 'paris', 'start_offset' => 11, 'end_offset' => 16, 'type' => '<ALPHANUM>', 'position' => 3],
            ],
            array_map(static fn (Token $t): array => $t->toArray(), $tokens),
        );
    }

    public function testOffsetsCanBeUsedForHighlighting(): void
    {
        $text = 'Un été à Montréal';
        $engine = (new TokenizerEngine())->addTokenizer(new StandardTokenizer())->addTokenFilter(new LowercaseTokenFilter())->addTokenFilter(new AsciiFoldingTokenFilter());

        $highlighted = '';
        $cursor = 0;
        foreach ($engine->tokens($text) as $token) {
            if ('montreal' === $token->term) {
                $highlighted .= mb_substr($text, $cursor, (int) $token->startOffset - $cursor).'<em>'.mb_substr($text, (int) $token->startOffset, (int) $token->endOffset - (int) $token->startOffset).'</em>';
                $cursor = (int) $token->endOffset;
            }
        }
        $highlighted .= mb_substr($text, $cursor);

        self::assertSame('Un été à <em>Montréal</em>', $highlighted);
    }

    public function testAnalyze(): void
    {
        $result = $this->engine()->analyze('<b>Le</b> Café');

        self::assertSame([['input' => '<b>Le</b> Café', 'output' => 'Le Café']], $result['characterFilters'][HTMLStripCharacterFilter::class]);
        self::assertSame([['input' => 'Le Café', 'output' => ['Le', 'Café']]], $result['tokenizers'][StandardTokenizer::class]);
        self::assertSame(['le', 'café'], $result['tokenFilters'][LowercaseTokenFilter::class][0]['output']);
        self::assertSame(['cafe'], $result['final']);
        self::assertSame('cafe', $result['tokens'][0]['token']);
    }

    public function testThirdPartyComponentsWorkingOnStrings(): void
    {
        $tokenizer = new class implements TokenizerInterface {
            public function tokenize(string|array $input): array
            {
                return ['x', 'y'];
            }
        };
        $filter = new class implements TokenFilterInterface {
            public function filter(array $tokens): array
            {
                return array_map(static fn (string|Token $t): string => strtoupper((string) $t), $tokens);
            }
        };

        self::assertSame(['X', 'Y'], (new TokenizerEngine())->addTokenizer($tokenizer)->addTokenFilter($filter)->tokenize('ignored'));
    }

    public function testFiltersAcceptTokensAndKeepThem(): void
    {
        $result = (new WordDelimiterTokenFilter())->filter([new Token('Wi-Fi', 4, 0, 5)]);

        self::assertContainsOnlyInstancesOf(Token::class, $result);
    }
}
