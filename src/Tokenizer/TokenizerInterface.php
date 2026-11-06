<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

use TokenizerEngine\Token;

interface TokenizerInterface
{
    /**
     * Splits text into tokens.
     *
     * - a string or a list of strings gives a list of strings;
     * - a list of Token objects gives a list of Token objects, each new token
     *   carrying offsets relative to the original text and a new position.
     *
     * @param string|array<array-key, string|Token> $input
     *
     * @return list<string>|list<Token>
     */
    public function tokenize(string|array $input): array;
}
