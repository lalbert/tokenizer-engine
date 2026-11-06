<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Splits text on a set of characters, like Elasticsearch's `char_group`
 * tokenizer. Each entry is a single character ("-", "\n") or a class name:
 * whitespace, letter, digit, punctuation, symbol.
 */
final class CharGroupTokenizer extends AbstractTokenizer
{
    private const CLASSES = [
        'whitespace' => '\s',
        'letter' => '\p{L}\p{M}',
        'digit' => '\p{Nd}',
        'punctuation' => '\p{P}',
        'symbol' => '\p{S}',
    ];

    private readonly string $pattern;

    /**
     * @param list<string> $tokenizeOnChars
     */
    public function __construct(array $tokenizeOnChars = ['whitespace'], private readonly int $maxTokenLength = 255)
    {
        if ([] === $tokenizeOnChars) {
            throw new \InvalidArgumentException('tokenizeOnChars must not be empty.');
        }

        $class = '';
        foreach ($tokenizeOnChars as $char) {
            if (isset(self::CLASSES[$char])) {
                $class .= self::CLASSES[$char];
                continue;
            }

            $char = stripcslashes($char);
            if (1 !== mb_strlen($char)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is neither a single character nor a known class.', $char));
            }
            $class .= preg_quote($char, '/');
        }

        $this->pattern = '/[^'.$class.']+/u';
    }

    protected function split(string $text): iterable
    {
        return self::limitLength(self::matches($this->pattern, $text), $this->maxTokenLength);
    }
}
