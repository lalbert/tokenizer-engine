<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Splits text on Unicode whitespace, like Elasticsearch's `whitespace` tokenizer.
 */
final class WhitespaceTokenizer extends AbstractTokenizer
{
    public function __construct(private readonly int $maxTokenLength = 255)
    {
        if ($maxTokenLength < 1) {
            throw new \InvalidArgumentException('maxTokenLength must be greater than 0.');
        }
    }

    protected function split(string $text): iterable
    {
        return self::limitLength(self::matches('/\S+/u', $text), $this->maxTokenLength);
    }
}
