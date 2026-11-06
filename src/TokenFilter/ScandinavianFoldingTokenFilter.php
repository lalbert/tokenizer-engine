<?php

/*
 * Port of Apache Lucene's ScandinavianFoldingFilter
 * (org.apache.lucene.analysis.miscellaneous), used by Elasticsearch's
 * `scandinavian_folding` filter.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 */

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Folds Scandinavian characters to plain letters so that Danish, Norwegian
 * and Swedish spellings match: å, ä, æ become a; ö, ø become o; aa, ae, ao
 * become a; oe, oo become o ("blåbærsyltetøj" and "blaabaersyltetoej" both
 * give "blabarsyltetoj").
 */
final class ScandinavianFoldingTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        $s = mb_str_split($term, 1, 'UTF-8');
        $length = \count($s);

        for ($i = 0; $i < $length; ++$i) {
            $c = $s[$i];

            if ('å' === $c || 'ä' === $c || 'æ' === $c) {
                $s[$i] = 'a';
            } elseif ('Å' === $c || 'Ä' === $c || 'Æ' === $c) {
                $s[$i] = 'A';
            } elseif ('ø' === $c || 'ö' === $c) {
                $s[$i] = 'o';
            } elseif ('Ø' === $c || 'Ö' === $c) {
                $s[$i] = 'O';
            } elseif ($length - 1 > $i) {
                $next = $s[$i + 1];
                if (('a' === $c || 'A' === $c) && \in_array($next, ['a', 'A', 'e', 'E', 'o', 'O'], true)) {
                    array_splice($s, $i + 1, 1);
                    --$length;
                } elseif (('o' === $c || 'O' === $c) && \in_array($next, ['e', 'E', 'o', 'O'], true)) {
                    array_splice($s, $i + 1, 1);
                    --$length;
                }
            }
        }

        return implode('', $s);
    }
}
