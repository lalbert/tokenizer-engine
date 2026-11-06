<?php

/*
 * Port of Apache Lucene's DictionaryCompoundWordTokenFilter
 * (org.apache.lucene.analysis.compound), used by Elasticsearch's
 * `dictionary_decompounder` filter. Licensed to the Apache Software
 * Foundation (ASF) under the Apache License, Version 2.0
 * (https://www.apache.org/licenses/LICENSE-2.0).
 */

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Finds the words of a dictionary inside compound words ("donaudampfschiff"
 * with donau, dampf, schiff gives donaudampfschiff, donau, dampf, schiff),
 * by brute force on every substring, like Elasticsearch's
 * `dictionary_decompounder` filter. HyphenationDecompounderTokenFilter is
 * more precise and faster on large dictionaries.
 *
 * With $onlyLongestMatch, only the longest word found at each position is
 * kept, and the search goes on after it. This is what Elasticsearch 9 does
 * whatever its `only_longest_match` setting (measured on 9.5), so filters
 * loaded from Elasticsearch settings use it.
 */
final class DictionaryDecompounderTokenFilter extends AbstractDecompounderTokenFilter
{
    protected function decompose(array $chars): array
    {
        $subwords = [];
        $length = \count($chars);

        for ($i = 0; $i <= $length - $this->minSubwordSize; ++$i) {
            $longest = null;

            for ($j = $this->minSubwordSize; $j <= $this->maxSubwordSize && $i + $j <= $length; ++$j) {
                if (!$this->inDictionary($chars, $i, $j)) {
                    continue;
                }

                if ($this->onlyLongestMatch) {
                    $longest = [$i, $j];
                } else {
                    $subwords[] = [$i, $j];
                }
            }

            if (null !== $longest) {
                $subwords[] = $longest;
                $i += $longest[1] - 1;
            }
        }

        return $subwords;
    }
}
