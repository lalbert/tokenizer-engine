<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Sorts and deduplicates the terms, then concatenates them into a single
 * token, like Elasticsearch's `fingerprint` filter (OpenRefine clustering).
 * If the result is longer than $maxOutputSize, no token is emitted.
 */
final class FingerprintTokenFilter extends AbstractTokenFilter
{
    public function __construct(private readonly string $separator = ' ', private readonly int $maxOutputSize = 255)
    {
        if (1 !== mb_strlen($separator, 'UTF-8')) {
            throw new \InvalidArgumentException(\sprintf('Setting [separator] must be a single, non-null character. [%s] was provided.', $separator));
        }

        if ($maxOutputSize < 1) {
            throw new \InvalidArgumentException('maxOutputSize must be greater than 0.');
        }
    }

    protected function process(array $tokens): array
    {
        $terms = array_values(array_unique(array_map(static fn (Token $token): string => $token->term, $tokens)));
        sort($terms, \SORT_STRING);

        $fingerprint = implode($this->separator, $terms);
        if (mb_strlen($fingerprint, 'UTF-8') > $this->maxOutputSize) {
            return [];
        }

        $starts = array_filter(array_map(static fn (Token $t): ?int => $t->startOffset, $tokens), 'is_int');
        $ends = array_filter(array_map(static fn (Token $t): ?int => $t->endOffset, $tokens), 'is_int');

        return [new Token(
            $fingerprint,
            0,
            [] === $starts ? null : min($starts),
            [] === $ends ? null : max($ends),
            'fingerprint',
        )];
    }
}
