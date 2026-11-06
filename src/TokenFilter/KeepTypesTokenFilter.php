<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Keeps (mode "include") or removes (mode "exclude") tokens of the given
 * types (<ALPHANUM>, <NUM>, <URL>, <EMAIL>, SYNONYM, shingle...), like
 * Elasticsearch's `keep_types` filter.
 */
final class KeepTypesTokenFilter extends AbstractTermTokenFilter
{
    /**
     * @var array<string, true>
     */
    private readonly array $types;

    /**
     * @param list<string> $types
     */
    public function __construct(array $types, private readonly string $mode = 'include')
    {
        if (!\in_array($mode, ['include', 'exclude'], true)) {
            throw new \InvalidArgumentException('mode must be "include" or "exclude".');
        }

        $this->types = array_fill_keys($types, true);
    }

    protected function filterTerm(string $term, Token $token): ?string
    {
        return isset($this->types[$token->type]) === ('include' === $this->mode) ? $term : null;
    }
}
