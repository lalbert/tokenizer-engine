<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Runs several filter chains on each token and emits all results at the same
 * position, like Elasticsearch's `multiplexer` filter. Duplicates are
 * removed.
 *
 * Example: [[new LowercaseTokenFilter()], [new LowercaseTokenFilter(), new StemmerTokenFilter('english')]]
 */
final class MultiplexerTokenFilter extends AbstractTokenFilter
{
    /**
     * @param list<list<TokenFilterInterface>> $chains
     */
    public function __construct(private readonly array $chains, private readonly bool $preserveOriginal = true)
    {
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $emitted = $this->preserveOriginal ? [$token->term => $token] : [];

            foreach ($this->chains as $chain) {
                $stream = [$token];
                foreach ($chain as $filter) {
                    /** @var list<Token> $stream */
                    $stream = $filter->filter($stream);
                }
                foreach ($stream as $output) {
                    $emitted[$output->term] ??= $output;
                }
            }

            array_push($result, ...array_values($emitted));
        }

        return $result;
    }
}
