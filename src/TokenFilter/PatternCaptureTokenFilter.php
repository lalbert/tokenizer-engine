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
        $captures = [];

        foreach ($this->regexes as $regex) {
            preg_match_all($regex, $term, $matches, \PREG_SET_ORDER);
            foreach ($matches as $match) {
                foreach (\array_slice($match, 1) as $group) {
                    if ('' !== $group) {
                        $captures[$group] = true;
                    }
                }
            }
        }

        if ([] === $captures) {
            return [$term];
        }

        $terms = array_map('strval', array_keys($captures));
        if ($this->preserveOriginal) {
            $terms = array_values(array_unique([$term, ...$terms]));
        }

        return $terms;
    }
}
