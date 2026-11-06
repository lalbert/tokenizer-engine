<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Emits the n-grams of each term ("fox" with 1..2 gives f, fo, o, ox, x),
 * like Elasticsearch's `ngram` filter.
 */
final class NGramTokenFilter extends AbstractNGramTokenFilter
{
    protected function nGrams(array $chars): array
    {
        $nGrams = [];
        $count = \count($chars);

        for ($start = 0; $start < $count; ++$start) {
            for ($length = $this->minGram; $length <= $this->maxGram && $start + $length <= $count; ++$length) {
                $nGrams[] = implode('', \array_slice($chars, $start, $length));
            }
        }

        return $nGrams;
    }
}
