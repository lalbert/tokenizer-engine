<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

use TokenizerEngine\Util\Text;

/**
 * Splits text with a regular expression, like Elasticsearch's `pattern`
 * tokenizer.
 *
 * The pattern is given without delimiters and is evaluated with Unicode
 * semantics. With $group = -1 the pattern matches separators; otherwise the
 * given capture group is emitted for each match (0 = whole match).
 */
final class PatternTokenizer extends AbstractTokenizer
{
    private readonly string $regex;

    public function __construct(string $pattern = '\W+', string $flags = '', private readonly int $group = -1)
    {
        $this->regex = Text::pattern($pattern, $flags);
    }

    protected function split(string $text): iterable
    {
        if ('' === $text) {
            return [];
        }

        if (-1 === $this->group) {
            $parts = preg_split($this->regex, $text, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_OFFSET_CAPTURE);
            if (false === $parts) {
                throw new \InvalidArgumentException('Input is not valid UTF-8.');
            }

            return self::toCharOffsets($text, $parts);
        }

        if (false === preg_match_all($this->regex, $text, $matches, \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER)) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        $parts = [];
        foreach ($matches as $match) {
            if (isset($match[$this->group]) && $match[$this->group][1] >= 0) {
                $parts[] = $match[$this->group];
            }
        }

        return self::toCharOffsets($text, $parts);
    }
}
