<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Emits the whole input as a single token, like Elasticsearch's `keyword`
 * tokenizer. Useful with FingerprintTokenFilter or for exact normalization.
 */
final class KeywordTokenizer extends AbstractTokenizer
{
    protected function split(string $text): iterable
    {
        return '' === $text ? [] : [[$text, 0]];
    }
}
