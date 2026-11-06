<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;
use TokenizerEngine\TokenFilter\ConditionalTokenFilter;
use TokenizerEngine\TokenFilter\DecimalDigitTokenFilter;
use TokenizerEngine\TokenFilter\IcuFoldingTokenFilter;
use TokenizerEngine\TokenFilter\IcuNormalizerTokenFilter;
use TokenizerEngine\TokenFilter\KeepTypesTokenFilter;
use TokenizerEngine\TokenFilter\KeepWordsTokenFilter;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\MultiplexerTokenFilter;
use TokenizerEngine\TokenFilter\PatternCaptureTokenFilter;
use TokenizerEngine\TokenFilter\PatternReplaceTokenFilter;
use TokenizerEngine\TokenFilter\ShingleTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;
use TokenizerEngine\Tokenizer\UaxUrlEmailTokenizer;
use TokenizerEngine\TokenizerEngine;

final class NewTokenFiltersTest extends TestCase
{
    public function testShingle(): void
    {
        self::assertSame(
            ['please', 'please divide', 'divide', 'divide this', 'this', 'this sentence', 'sentence'],
            (new ShingleTokenFilter())->filter(['please', 'divide', 'this', 'sentence']),
        );
        self::assertSame(['a b', 'a b c', 'b c'], (new ShingleTokenFilter(2, 3, false))->filter(['a', 'b', 'c']));
    }

    public function testShingleFillerAndOffsets(): void
    {
        $result = (new ShingleTokenFilter(outputUnigrams: false))->filter([new Token('please', 0, 0, 6), new Token('divide', 1, 7, 13), new Token('sentence', 3, 19, 27)]);

        self::assertSame(
            // A filler takes the start offset of the next real token, like Elasticsearch.
            [['please divide', 0, 0, 13], ['divide _', 1, 7, 19], ['_ sentence', 2, 19, 27]],
            array_map(static fn (Token $t): array => [$t->term, $t->position, $t->startOffset, $t->endOffset], $result),
        );
    }

    public function testShingleUnigramsIfNoShingles(): void
    {
        self::assertSame(['alone'], (new ShingleTokenFilter(outputUnigrams: false, outputUnigramsIfNoShingles: true))->filter(['alone']));
        self::assertSame([], (new ShingleTokenFilter(outputUnigrams: false))->filter(['alone']));
    }

    public function testKeepWords(): void
    {
        self::assertSame(['dog', 'Fox'], (new KeepWordsTokenFilter(['dog', 'fox'], ignoreCase: true))->filter(['the', 'dog', 'Fox']));
        self::assertSame(['dog'], (new KeepWordsTokenFilter(['dog', 'fox']))->filter(['the', 'dog', 'Fox']));
    }

    public function testKeepTypes(): void
    {
        $engine = (new TokenizerEngine())->addTokenizer(new UaxUrlEmailTokenizer());

        self::assertSame(['luc@ex.fr'], self::terms((new KeepTypesTokenFilter(['<EMAIL>']))->filter($engine->tokens('mail luc@ex.fr 42'))));
        self::assertSame(['mail', 'luc@ex.fr'], self::terms((new KeepTypesTokenFilter(['<NUM>'], 'exclude'))->filter($engine->tokens('mail luc@ex.fr 42'))));
    }

    public function testPatternReplace(): void
    {
        self::assertSame(['wtt', 'wt'], (new PatternReplaceTokenFilter('[aeiou]', ''))->filter(['watt', 'wit']));
        self::assertSame(['b-ac'], (new PatternReplaceTokenFilter('a', 'b-', false))->filter(['aac']));
    }

    public function testPatternCapture(): void
    {
        // Captures are ordered by position in the term and duplicates are kept, like Elasticsearch.
        self::assertSame(['abc123def456', 'abc', '123', 'def', '456'], (new PatternCaptureTokenFilter(['([a-z]+)', '(\d+)']))->filter(['abc123def456']));
        self::assertSame(['abc', '123', 'def', '456'], (new PatternCaptureTokenFilter(['([a-z]+)', '(\d+)'], false))->filter(['abc123def456']));
        self::assertSame(['Wi-Fi', 'i', 'i'], (new PatternCaptureTokenFilter(['(\p{Ll}+)']))->filter(['Wi-Fi']));
        self::assertSame(['abc'], (new PatternCaptureTokenFilter(['([a-z]+)']))->filter(['abc']));
        self::assertSame(['---'], (new PatternCaptureTokenFilter(['([a-z]+)']))->filter(['---']));
    }

    public function testMultiplexer(): void
    {
        $filter = new MultiplexerTokenFilter([[new LowercaseTokenFilter()], [new LowercaseTokenFilter(), new StemmerTokenFilter('english')]]);

        self::assertSame(['Running', 'running', 'run', 'Dogs', 'dogs', 'dog'], $filter->filter(['Running', 'Dogs']));
        self::assertSame([3, 3, 3], array_map(static fn (Token $t): int => $t->position, $filter->filter([new Token('Running', 3)])));
    }

    public function testConditional(): void
    {
        $filter = new ConditionalTokenFilter(static fn (Token $t): bool => mb_strlen($t->term) < 5, [new LowercaseTokenFilter()]);

        self::assertSame(['what', 'EVERYONE'], $filter->filter(['WHAT', 'EVERYONE']));
    }

    public function testDecimalDigit(): void
    {
        self::assertSame(['34', '12', '12', '3', 'a1'], (new DecimalDigitTokenFilter())->filter(['٣٤', '१२', '１２', '𝟑', 'a1']));
    }

    public function testIcu(): void
    {
        if (!\extension_loaded('intl')) {
            self::markTestSkipped('ext-intl is not installed.');
        }

        self::assertSame(['fiécole', 'strasse'], (new IcuNormalizerTokenFilter())->filter(['ﬁÉCOLE', 'Straße']));
        self::assertSame(['ﬁÉCOLE'], (new IcuNormalizerTokenFilter('nfc'))->filter(['ﬁÉCOLE']));
        self::assertSame(['mussli', 'fiecole', 'xii'], (new IcuFoldingTokenFilter())->filter(['Ｍüßli', 'ﬁÉcole', 'Ⅻ']));
    }

    /**
     * @param list<string>|list<Token> $tokens
     *
     * @return list<string>
     */
    private static function terms(array $tokens): array
    {
        return array_map(static fn (string|Token $t): string => (string) $t, $tokens);
    }
}
