<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Splits text on anything that is not a letter, like Elasticsearch's
 * `letter` tokenizer.
 */
final class LetterTokenizer extends AbstractTokenizer
{
    protected function split(string $text): iterable
    {
        return self::matches('/[\p{L}\p{M}]+/u', $text);
    }
}
