<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Tokens;

/**
 * Base class handling the string/Token duality of TokenFilterInterface.
 * Subclasses only deal with lists of Token objects.
 */
abstract class AbstractTokenFilter implements TokenFilterInterface
{
    public function filter(array $tokens): array
    {
        if ([] === $tokens) {
            return [];
        }

        $returnTokens = Tokens::containsTokenObjects($tokens);
        $result = $this->process(Tokens::normalize($tokens));

        return $returnTokens ? $result : Tokens::terms($result);
    }

    /**
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    abstract protected function process(array $tokens): array;
}
