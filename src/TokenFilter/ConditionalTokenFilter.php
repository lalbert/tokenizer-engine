<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Tokens;

/**
 * Applies filters only to the tokens matching a predicate, like
 * Elasticsearch's `condition` filter (which uses a Painless script; here
 * the predicate is a PHP callable, so this filter cannot be loaded from
 * Elasticsearch settings).
 *
 * Example: new ConditionalTokenFilter(fn (Token $t) => mb_strlen($t->term) < 5, [new LowercaseTokenFilter()])
 */
final class ConditionalTokenFilter extends AbstractTokenFilter
{
    /**
     * @var \Closure(Token): bool
     */
    private readonly \Closure $predicate;

    /**
     * @param callable(Token): bool      $predicate
     * @param list<TokenFilterInterface> $filters
     */
    public function __construct(callable $predicate, private readonly array $filters)
    {
        $this->predicate = $predicate(...);
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if (!($this->predicate)($token)) {
                $result[] = $token;
                continue;
            }

            $stream = [$token];
            foreach ($this->filters as $filter) {
                $stream = Tokens::normalize($filter->filter($stream));
            }
            array_push($result, ...$stream);
        }

        return $result;
    }
}
