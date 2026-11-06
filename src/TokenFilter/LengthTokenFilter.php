<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Removes terms shorter than $min or longer than $max characters, like
 * Elasticsearch's `length` filter.
 */
final class LengthTokenFilter extends AbstractTermTokenFilter
{
    public function __construct(private readonly int $min = 0, private readonly int $max = \PHP_INT_MAX)
    {
        if ($min < 0 || $max < $min) {
            throw new \InvalidArgumentException('Expected 0 <= min <= max.');
        }
    }

    protected function filterTerm(string $term, Token $token): ?string
    {
        $length = mb_strlen($term, 'UTF-8');

        return $length >= $this->min && $length <= $this->max ? $term : null;
    }
}
