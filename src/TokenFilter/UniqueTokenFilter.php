<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Removes duplicate terms, like Elasticsearch's `unique` filter. With
 * $onlyOnSamePosition, only duplicates at the same position are removed.
 */
final class UniqueTokenFilter extends AbstractTokenFilter
{
    public function __construct(private readonly bool $onlyOnSamePosition = false)
    {
    }

    protected function process(array $tokens): array
    {
        $seen = [];
        $result = [];

        foreach ($tokens as $token) {
            $key = ($this->onlyOnSamePosition ? $token->position."\0" : '').$token->term;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $token;
        }

        return $result;
    }
}
