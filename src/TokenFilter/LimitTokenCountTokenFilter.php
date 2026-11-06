<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Keeps only the first $maxTokenCount tokens, like Elasticsearch's `limit`
 * filter.
 */
final class LimitTokenCountTokenFilter extends AbstractTokenFilter
{
    public function __construct(private readonly int $maxTokenCount = 1)
    {
        if ($maxTokenCount <= 0) {
            throw new \InvalidArgumentException('$maxTokenCount must be greater than zero.');
        }
    }

    protected function process(array $tokens): array
    {
        return \array_slice($tokens, 0, $this->maxTokenCount);
    }
}
