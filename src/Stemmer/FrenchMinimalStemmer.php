<?php

/*
 * Port of Apache Lucene's FrenchMinimalStemmer
 * (org.apache.lucene.analysis.fr.FrenchMinimalStemmer), the stemmer used by
 * Elasticsearch's `minimal_french` stemmer. Algorithm by Jacques Savoy.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 */

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

final class FrenchMinimalStemmer implements StemmerInterface
{
    public function stem(string $word): string
    {
        $s = mb_str_split($word, 1, 'UTF-8');
        $len = \count($s);

        if ($len < 6) {
            return $word;
        }

        if ('x' === $s[$len - 1]) {
            if ('a' === $s[$len - 3] && 'u' === $s[$len - 2]) {
                $s[$len - 2] = 'l';
            }

            return implode('', \array_slice($s, 0, $len - 1));
        }

        if ('s' === $s[$len - 1]) {
            --$len;
        }
        if ('r' === $s[$len - 1]) {
            --$len;
        }
        if ('e' === $s[$len - 1]) {
            --$len;
        }
        if ('é' === $s[$len - 1]) {
            --$len;
        }
        if ($s[$len - 1] === $s[$len - 2] && preg_match('/^\p{L}$/u', $s[$len - 1])) {
            --$len;
        }

        return implode('', \array_slice($s, 0, $len));
    }
}
