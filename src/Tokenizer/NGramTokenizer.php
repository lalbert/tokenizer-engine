<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Emits the n-grams of the text ("Fox" gives F, Fo, o, ox, x), like
 * Elasticsearch's `ngram` tokenizer.
 */
final class NGramTokenizer extends AbstractNGramTokenizer
{
    protected function nGrams(array $chars): iterable
    {
        $count = \count($chars);

        for ($start = 0; $start < $count; ++$start) {
            for ($length = $this->minGram; $length <= $this->maxGram && $start + $length <= $count; ++$length) {
                yield [implode('', \array_slice($chars, $start, $length)), $start];
            }
        }
    }
}
