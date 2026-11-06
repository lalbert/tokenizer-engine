<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Tokens;

abstract class AbstractTokenizer implements TokenizerInterface
{
    public function tokenize(string|array $input): array
    {
        if (\is_string($input)) {
            return Tokens::terms($this->tokenizeTokens([self::textToken($input)]));
        }

        $returnTokens = Tokens::containsTokenObjects($input);
        $tokens = [];
        foreach ($input as $item) {
            $tokens[] = $item instanceof Token ? $item : self::textToken((string) $item);
        }

        $result = $this->tokenizeTokens($tokens);

        return $returnTokens ? $result : Tokens::terms($result);
    }

    /**
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    public function tokenizeTokens(array $tokens): array
    {
        $result = [];
        $position = 0;

        foreach ($tokens as $token) {
            $base = $token->hasExactOffsets() ? $token->startOffset : null;

            foreach ($this->split($token->term) as [$term, $offset]) {
                $result[] = new Token(
                    $term,
                    $position++,
                    null === $base ? $token->startOffset : $base + $offset,
                    null === $base ? $token->endOffset : $base + $offset + mb_strlen($term),
                    $this->tokenType($term),
                );
            }
        }

        return $result;
    }

    /**
     * Returns the terms found in $text with their offset (in code points).
     *
     * @return iterable<array{0: string, 1: int}>
     */
    abstract protected function split(string $text): iterable;

    protected function tokenType(string $term): string
    {
        return Token::TYPE_WORD;
    }

    /**
     * Splits terms longer than $maxLength code points, like Elasticsearch.
     *
     * @param iterable<array{0: string, 1: int}> $parts
     *
     * @return iterable<array{0: string, 1: int}>
     */
    protected static function limitLength(iterable $parts, int $maxLength): iterable
    {
        foreach ($parts as [$term, $offset]) {
            if (mb_strlen($term) <= $maxLength) {
                yield [$term, $offset];
                continue;
            }

            foreach (mb_str_split($term, max(1, $maxLength)) as $i => $chunk) {
                yield [$chunk, $offset + $i * $maxLength];
            }
        }
    }

    /**
     * Returns regex matches of $pattern in $text with code point offsets.
     *
     * @return list<array{0: string, 1: int}>
     */
    protected static function matches(string $pattern, string $text): array
    {
        if (false === preg_match_all($pattern, $text, $matches, \PREG_OFFSET_CAPTURE)) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        return self::toCharOffsets($text, $matches[0]);
    }

    /**
     * @param array<array{0: string, 1: int}> $byteMatches
     *
     * @return list<array{0: string, 1: int}>
     */
    protected static function toCharOffsets(string $text, array $byteMatches): array
    {
        $result = [];
        $lastByte = 0;
        $lastChar = 0;

        foreach ($byteMatches as [$term, $byteOffset]) {
            if ('' === $term) {
                continue;
            }
            $lastChar += mb_strlen(substr($text, $lastByte, $byteOffset - $lastByte));
            $lastByte = $byteOffset;
            $result[] = [$term, $lastChar];
        }

        return $result;
    }

    private static function textToken(string $text): Token
    {
        return new Token($text, 0, 0, mb_strlen($text));
    }
}
