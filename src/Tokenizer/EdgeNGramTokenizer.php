<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Emits the prefixes of each word ("Quick Fox" with token chars "letter"
 * gives Q, Qu, F, Fo), like Elasticsearch's `edge_ngram` tokenizer. Typically
 * used for search-as-you-type.
 */
final class EdgeNGramTokenizer extends AbstractNGramTokenizer
{
    protected function nGrams(array $chars): iterable
    {
        $count = \count($chars);

        for ($length = $this->minGram; $length <= $this->maxGram && $length <= $count; ++$length) {
            yield [implode('', \array_slice($chars, 0, $length)), 0];
        }
    }
}
