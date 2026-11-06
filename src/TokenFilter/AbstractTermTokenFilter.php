<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Base class for filters that transform each term independently.
 *
 * filterTerm() may return:
 *  - a string: the token is replaced (position and offsets are kept);
 *  - a list of strings: every string becomes a token at the same position;
 *  - null or an empty list: the token is removed.
 */
abstract class AbstractTermTokenFilter extends AbstractTokenFilter
{
    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $terms = $this->filterTerm($token->term, $token);

            if (null === $terms) {
                continue;
            }

            if (\is_string($terms)) {
                $result[] = $terms === $token->term ? $token : $token->withTerm($terms);
                continue;
            }

            foreach ($terms as $term) {
                $result[] = $term === $token->term ? $token : $token->withTerm($term);
            }
        }

        return $result;
    }

    /**
     * @return string|list<string>|null
     */
    abstract protected function filterTerm(string $term, Token $token): string|array|null;
}
