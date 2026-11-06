<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\OffsetMap;
use TokenizerEngine\Util\OffsetRewriter;
use TokenizerEngine\Util\WordList;

/**
 * Replaces strings using a mapping, like Elasticsearch's `mapping` character
 * filter. The longest match wins.
 *
 * Mappings can be an associative array (['٠' => '0']) or a list of
 * Elasticsearch style rules (['٠ => 0', ':) => _happy_']).
 */
final class MappingCharacterFilter implements OffsetMappingCharacterFilterInterface
{
    /**
     * @var array<string, string>
     */
    private readonly array $mappings;

    /**
     * @param array<array-key, string> $mappings
     */
    public function __construct(array $mappings = [], ?string $mappingsPath = null)
    {
        $rules = [];

        if (null !== $mappingsPath) {
            $mappings = array_merge($mappings, WordList::lines($mappingsPath));
        }

        foreach ($mappings as $key => $value) {
            if (\is_string($key)) {
                $rules[$key] = $value;
                continue;
            }

            if (!preg_match('/^(.+?)\s*=>\s*(.*)$/su', $value, $match)) {
                throw new \InvalidArgumentException(\sprintf('Invalid mapping "%s", expected "key => value".', $value));
            }
            $rules[self::unescape($match[1])] = self::unescape($match[2]);
        }

        unset($rules['']);
        $this->mappings = $rules;
    }

    public function filter(string $text): string
    {
        return [] === $this->mappings ? $text : strtr($text, $this->mappings);
    }

    public function filterWithOffsets(string $text): array
    {
        if ([] === $this->mappings) {
            return [$text, OffsetMap::identity()];
        }

        // Longest keys first, like strtr().
        $keys = array_map('strval', array_keys($this->mappings));
        usort($keys, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $regex = '~'.implode('|', array_map(static fn (string $k): string => preg_quote($k, '~'), $keys)).'~su';

        return OffsetRewriter::rewrite($text, $regex, fn (array $m): array => [$this->mappings[$m[0]], false]);
    }

    private static function unescape(string $value): string
    {
        return stripcslashes(trim($value));
    }
}
