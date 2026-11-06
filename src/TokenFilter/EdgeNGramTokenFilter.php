<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Emits the prefixes of each term ("quick" with 1..3 gives q, qu, qui), like
 * Elasticsearch's `edge_ngram` filter. Typically used for autocompletion.
 */
final class EdgeNGramTokenFilter extends AbstractNGramTokenFilter
{
    protected function nGrams(array $chars): array
    {
        $nGrams = [];
        $count = \count($chars);

        for ($length = $this->minGram; $length <= $this->maxGram && $length <= $count; ++$length) {
            $nGrams[] = implode('', \array_slice($chars, 0, $length));
        }

        return $nGrams;
    }
}
