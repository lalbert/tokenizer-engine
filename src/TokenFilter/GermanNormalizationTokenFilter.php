<?php

/*
 * Port of Apache Lucene's GermanNormalizationFilter
 * (org.apache.lucene.analysis.de), used by Elasticsearch's
 * `german_normalization` filter and `german` analyzer.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 */

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Normalizes German spellings (German2 snowball rules): "ä", "ö", "ü" become
 * "a", "o", "u", "ae", "oe", "ue" become "a", "o", "u" (except after a
 * vowel), and "ß" becomes "ss". Expects lowercased terms.
 */
final class GermanNormalizationTokenFilter extends AbstractTermTokenFilter
{
    private const N = 0; // ordinary state
    private const V = 1; // stops "u" from entering the umlaut state
    private const U = 2; // umlaut state, allows e-deletion

    protected function filterTerm(string $term, Token $token): string
    {
        $state = self::N;
        $result = '';

        foreach (mb_str_split($term, 1, 'UTF-8') as $char) {
            switch ($char) {
                case 'a':
                case 'o':
                    $state = self::U;
                    break;
                case 'u':
                    $state = self::N === $state ? self::U : self::V;
                    break;
                case 'e':
                    if (self::U === $state) {
                        $state = self::V;
                        continue 2; // deleted
                    }
                    $state = self::V;
                    break;
                case 'i':
                case 'q':
                case 'y':
                    $state = self::V;
                    break;
                case 'ä':
                    $char = 'a';
                    $state = self::V;
                    break;
                case 'ö':
                    $char = 'o';
                    $state = self::V;
                    break;
                case 'ü':
                    $char = 'u';
                    $state = self::V;
                    break;
                case 'ß':
                    $char = 'ss';
                    $state = self::N;
                    break;
                default:
                    $state = self::N;
            }
            $result .= $char;
        }

        return $result;
    }
}
