<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\Util\Text;

/**
 * Replaces regular expression matches, like Elasticsearch's
 * `pattern_replace` character filter. The pattern has no delimiters; the
 * replacement can reference groups with $1 (Java/Elasticsearch syntax).
 */
final class PatternReplaceCharacterFilter implements CharacterFilterInterface
{
    private readonly string $regex;

    public function __construct(string $pattern, private readonly string $replacement = '', string $flags = '')
    {
        $this->regex = Text::pattern($pattern, $flags);
    }

    public function filter(string $text): string
    {
        $result = preg_replace($this->regex, $this->replacement, $text);
        if (null === $result) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        return $result;
    }
}
