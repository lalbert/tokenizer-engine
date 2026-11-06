<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Text;

/**
 * Replaces each term with the closest word of a dictionary (Levenshtein
 * distance computed on characters, not bytes). Terms farther than
 * $maxDistance from every word are kept unchanged. On ties, the first word of
 * the dictionary wins.
 *
 * Not an Elasticsearch filter: useful for typo correction on small
 * vocabularies (cities, product names...).
 */
final class LevenshteinTokenFilter extends AbstractTermTokenFilter
{
    /**
     * @var list<string>
     */
    private readonly array $words;

    /**
     * @param list<string> $words
     */
    public function __construct(array $words = [], private readonly int $maxDistance = \PHP_INT_MAX)
    {
        if ($maxDistance < 0) {
            throw new \InvalidArgumentException('maxDistance must be greater than or equal to 0.');
        }

        $this->words = array_values(array_unique($words));
    }

    protected function filterTerm(string $term, Token $token): string
    {
        $closest = null;
        $shortest = \PHP_INT_MAX;
        $termLength = mb_strlen($term);

        foreach ($this->words as $word) {
            if ($word === $term) {
                return $term;
            }

            // The length difference is a lower bound of the distance.
            $lowerBound = abs($termLength - mb_strlen($word));
            if ($lowerBound > $this->maxDistance || $lowerBound >= $shortest) {
                continue;
            }

            $distance = Text::levenshtein($term, $word);
            if ($distance <= $this->maxDistance && $distance < $shortest) {
                $closest = $word;
                $shortest = $distance;
            }
        }

        return $closest ?? $term;
    }
}
