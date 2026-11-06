<?php

/*
 * Port of Apache Lucene's HyphenationCompoundWordTokenFilter
 * (org.apache.lucene.analysis.compound), used by Elasticsearch's
 * `hyphenation_decompounder` filter. Licensed to the Apache Software
 * Foundation (ASF) under the Apache License, Version 2.0
 * (https://www.apache.org/licenses/LICENSE-2.0).
 */

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Hyphenation\HyphenationTree;

/**
 * Splits compound words at their hyphenation points, then keeps the parts
 * found in the dictionary ("Kaffeetasse" hyphenated kaf-fee-tas-se gives
 * kaffee and tasse), like Elasticsearch's `hyphenation_decompounder`
 * filter. Hyphenation patterns use the XML format of Apache FOP.
 *
 * - $onlyLongestMatch: only the longest subword starting at each point;
 * - $noSubMatches: no subword inside a subword already found;
 * - $noOverlappingMatches: subwords do not overlap.
 */
final class HyphenationDecompounderTokenFilter extends AbstractDecompounderTokenFilter
{
    private readonly bool $calcSubMatches;

    /**
     * @param list<string> $wordList
     */
    public function __construct(
        private readonly HyphenationTree $hyphenator,
        array $wordList = [],
        ?string $wordListPath = null,
        int $minWordSize = 5,
        int $minSubwordSize = 2,
        int $maxSubwordSize = 15,
        bool $onlyLongestMatch = false,
        private readonly bool $noSubMatches = false,
        private readonly bool $noOverlappingMatches = false,
    ) {
        parent::__construct($wordList, $wordListPath, $minWordSize, $minSubwordSize, $maxSubwordSize, $onlyLongestMatch);
        $this->calcSubMatches = !$onlyLongestMatch && !$noSubMatches && !$noOverlappingMatches;
    }

    protected function decompose(array $chars): array
    {
        $length = \count($chars);

        // A word of the dictionary is not decomposed (unless sub-matches are wanted).
        if (!$this->calcSubMatches && ($this->inDictionary($chars, 0, $length) || ($length > 1 && $this->inDictionary($chars, 0, $length - 1)))) {
            return [];
        }

        $points = $this->hyphenator->hyphenate(implode('', $chars), 1, 1);
        if (null === $points) {
            return [];
        }

        $maxSubwordSize = min($this->maxSubwordSize, $length - 1);
        $consumed = -1;
        $subwords = [];
        $count = \count($points);

        for ($i = 0; $i < $count; ++$i) {
            if ($this->noOverlappingMatches) {
                $i = max($i, $consumed);
            }

            $start = $points[$i];
            $until = $this->noSubMatches ? max($consumed, $i) : $i;

            for ($j = $count - 1; $j > $until; --$j) {
                $partLength = $points[$j] - $start;
                if ($partLength > $maxSubwordSize) {
                    continue;
                }
                if ($partLength < $this->minSubwordSize) {
                    break;
                }

                if ([] === $this->dictionary || $this->inDictionary($chars, $start, $partLength)) {
                    $subwords[] = [$start, $partLength];
                } elseif ($this->inDictionary($chars, $start, $partLength - 1)) {
                    $subwords[] = [$start, $partLength - 1];
                } else {
                    continue;
                }

                $consumed = $j;
                if (!$this->calcSubMatches) {
                    break;
                }
            }
        }

        return $subwords;
    }
}
