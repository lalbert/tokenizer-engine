<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

use TokenizerEngine\Token;

/**
 * Emits every level of a path ("/a/b/c" gives /a, /a/b, /a/b/c), like
 * Elasticsearch's `path_hierarchy` tokenizer. With $reverse, emits the
 * suffixes instead (useful for domain names: "www.example.com").
 */
final class PathHierarchyTokenizer extends AbstractTokenizer
{
    private readonly string $replacement;

    public function __construct(
        private readonly string $delimiter = '/',
        ?string $replacement = null,
        private readonly bool $reverse = false,
        private readonly int $skip = 0,
    ) {
        if (1 !== mb_strlen($delimiter)) {
            throw new \InvalidArgumentException('The delimiter must be a single character.');
        }
        if ($skip < 0) {
            throw new \InvalidArgumentException('skip must be greater than or equal to 0.');
        }

        $this->replacement = $replacement ?? $delimiter;
    }

    public function tokenizeTokens(array $tokens): array
    {
        $result = [];
        $position = 0;

        foreach ($tokens as $token) {
            $exact = $token->hasExactOffsets();

            foreach ($this->levels($token->term) as [$term, $start, $end]) {
                $result[] = new Token(
                    str_replace($this->delimiter, $this->replacement, $term),
                    $position++,
                    $exact ? (int) $token->startOffset + $start : $token->startOffset,
                    $exact ? (int) $token->startOffset + $end : $token->endOffset,
                );
            }
        }

        return $result;
    }

    protected function split(string $text): iterable
    {
        foreach ($this->levels($text) as [$term, $start]) {
            yield [str_replace($this->delimiter, $this->replacement, $term), $start];
        }
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}> term, start, end (characters)
     */
    private function levels(string $text): array
    {
        if ('' === $text) {
            return [];
        }

        $quoted = preg_quote($this->delimiter, '/');
        // Forward: delimiter starts a segment ("/a" "/b"); reverse: it ends one ("/" "a/" "b").
        $pattern = $this->reverse ? '/[^'.$quoted.']*'.$quoted.'|[^'.$quoted.']+$/u' : '/'.$quoted.'?[^'.$quoted.']*/u';
        preg_match_all($pattern, $text, $matches);
        $segments = array_values(array_filter($matches[0], static fn (string $s): bool => '' !== $s));

        $offsets = [];
        $cursor = 0;
        foreach ($segments as $segment) {
            $length = mb_strlen($segment);
            $offsets[] = [$cursor, $cursor + $length];
            $cursor += $length;
        }

        $count = \count($segments);
        $levels = [];

        if ($this->reverse) {
            $last = $count - 1 - $this->skip;
            for ($i = 0; $i <= $last; ++$i) {
                $levels[] = [implode('', \array_slice($segments, $i, $last - $i + 1)), $offsets[$i][0], $offsets[$last][1]];
            }

            return $levels;
        }

        for ($i = $this->skip; $i < $count; ++$i) {
            $levels[] = [implode('', \array_slice($segments, $this->skip, $i - $this->skip + 1)), $offsets[$this->skip][0], $offsets[$i][1]];
        }

        return $levels;
    }
}
