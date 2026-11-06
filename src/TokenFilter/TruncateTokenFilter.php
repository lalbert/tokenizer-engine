<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Truncates terms to $length characters, like Elasticsearch's `truncate`
 * filter.
 */
final class TruncateTokenFilter extends AbstractTermTokenFilter
{
    public function __construct(private readonly int $length = 10)
    {
        if ($length <= 0) {
            throw new \InvalidArgumentException('$length must be a positive integer greater than 0.');
        }
    }

    protected function filterTerm(string $term, Token $token): string
    {
        return mb_substr($term, 0, $this->length, 'UTF-8');
    }
}
