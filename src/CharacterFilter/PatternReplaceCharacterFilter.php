<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\Util\OffsetRewriter;
use TokenizerEngine\Util\Text;

/**
 * Replaces regular expression matches, like Elasticsearch's
 * `pattern_replace` character filter. The pattern has no delimiters; the
 * replacement can reference groups with $1 (Java/Elasticsearch syntax).
 */
final class PatternReplaceCharacterFilter implements OffsetMappingCharacterFilterInterface
{
    private readonly string $regex;

    public function __construct(string $pattern, private readonly string $replacement = '', string $flags = '')
    {
        $this->regex = Text::pattern($pattern, $flags);
    }

    public function filterWithOffsets(string $text): array
    {
        return OffsetRewriter::rewrite($text, $this->regex, fn (array $m): array => [$this->expand($m), false]);
    }

    /**
     * Expands $1, ${1}, ${name} and \\$ in the replacement.
     *
     * @param array<int|string, string> $groups
     */
    private function expand(array $groups): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)|\$(\d+)|\$\{(\w+)\}/',
            static function (array $m) use ($groups): string {
                if (null !== ($m[1] ?? null)) {
                    return $m[1];
                }
                $key = null !== ($m[2] ?? null) ? (int) $m[2] : (ctype_digit((string) $m[3]) ? (int) $m[3] : (string) $m[3]);

                return $groups[$key] ?? '';
            },
            $this->replacement,
            -1,
            $count,
            \PREG_UNMATCHED_AS_NULL,
        );
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
