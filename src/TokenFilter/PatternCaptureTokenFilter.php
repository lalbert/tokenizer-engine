<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Text;

/**
 * Emits every capture group of every pattern match ("import_export" with
 * "([a-z]+)" gives import, export), like Elasticsearch's `pattern_capture`
 * filter. Terms matching no pattern are kept.
 */
final class PatternCaptureTokenFilter extends AbstractTermTokenFilter
{
    /**
     * @var list<string>
     */
    private readonly array $regexes;

    /**
     * @param list<string> $patterns
     */
    public function __construct(array $patterns, private readonly bool $preserveOriginal = true)
    {
        $this->regexes = array_values(array_map(static fn (string $p): string => Text::pattern($p), $patterns));
    }

    protected function filterTerm(string $term, Token $token): array
    {
        // Captures of every pattern, ordered by position in the term like
        // Elasticsearch; duplicates are kept, empty captures and captures equal
        // to the term itself are skipped.
        $captures = [];

        foreach ($this->regexes as $p => $regex) {
            preg_match_all($regex, $term, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
            foreach ($matches as $m => $match) {
                foreach (\array_slice($match, 1) as $g => [$group, $offset]) {
                    if ('' !== $group && $offset >= 0 && $group !== $term) {
                        $captures[] = [$offset, -\strlen($group), $p, $m, $g, $group];
                    }
                }
            }
        }

        if ([] === $captures) {
            return [$term];
        }

        usort($captures, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2], $a[3], $a[4]] <=> [$b[0], $b[1], $b[2], $b[3], $b[4]]);
        $terms = array_column($captures, 5);

        return $this->preserveOriginal ? [$term, ...$terms] : $terms;
    }
}
