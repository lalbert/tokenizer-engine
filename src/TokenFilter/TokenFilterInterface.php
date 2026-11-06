<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

interface TokenFilterInterface
{
    /**
     * Filters a token stream.
     *
     * Accepts plain strings (returns plain strings) or Token objects (returns
     * Token objects, keeping positions and offsets).
     *
     * @param array<array-key, string|Token> $tokens
     *
     * @return list<string>|list<Token>
     */
    public function filter(array $tokens): array;
}
