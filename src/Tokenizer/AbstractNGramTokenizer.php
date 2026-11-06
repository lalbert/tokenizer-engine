<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Base class of NGramTokenizer and EdgeNGramTokenizer.
 *
 * $tokenChars lists the character classes kept in tokens (letter, digit,
 * whitespace, punctuation, symbol, custom); the text is split on any other
 * character. An empty list keeps every character, like Elasticsearch.
 */
abstract class AbstractNGramTokenizer extends AbstractTokenizer
{
    private const CLASSES = [
        'letter' => '\p{L}\p{M}',
        'digit' => '\p{Nd}',
        'whitespace' => '\s',
        'punctuation' => '\p{P}',
        'symbol' => '\p{S}',
    ];

    private readonly ?string $splitPattern;

    /**
     * @param list<string> $tokenChars
     */
    public function __construct(
        protected readonly int $minGram = 1,
        protected readonly int $maxGram = 2,
        array $tokenChars = [],
        string $customTokenChars = '',
    ) {
        if ($minGram < 1 || $maxGram < $minGram) {
            throw new \InvalidArgumentException('Expected 1 <= minGram <= maxGram.');
        }

        $class = '';
        foreach ($tokenChars as $name) {
            if ('custom' === $name) {
                $class .= preg_quote($customTokenChars, '/');
                continue;
            }

            $class .= self::CLASSES[$name] ?? throw new \InvalidArgumentException(\sprintf('Unknown token_chars class "%s".', $name));
        }

        $this->splitPattern = [] === $tokenChars ? null : '/[^'.$class.']+/u';
    }

    protected function split(string $text): iterable
    {
        $runs = null === $this->splitPattern
            ? ('' === $text ? [] : [[$text, 0]])
            : self::toCharOffsets($text, preg_split($this->splitPattern, $text, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_OFFSET_CAPTURE) ?: []);

        foreach ($runs as [$run, $offset]) {
            foreach ($this->nGrams(mb_str_split($run, 1, 'UTF-8')) as [$gram, $start]) {
                yield [$gram, $offset + $start];
            }
        }
    }

    /**
     * @param list<string> $chars
     *
     * @return iterable<array{0: string, 1: int}>
     */
    abstract protected function nGrams(array $chars): iterable;
}
