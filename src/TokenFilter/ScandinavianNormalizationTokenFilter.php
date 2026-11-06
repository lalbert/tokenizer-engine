<?php

/*
 * Port of Apache Lucene's ScandinavianNormalizationFilter and
 * ScandinavianNormalizer (org.apache.lucene.analysis.miscellaneous), used by
 * Elasticsearch's `scandinavian_normalization` filter.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 */

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Normalizes interchangeable Scandinavian characters to their Danish and
 * Norwegian form, while keeping them (unlike ScandinavianFoldingTokenFilter):
 * ä becomes æ, ö becomes ø, aa becomes å, ae becomes æ, oe and oo become ø.
 */
final class ScandinavianNormalizationTokenFilter extends AbstractTermTokenFilter
{
    protected function filterTerm(string $term, Token $token): string
    {
        $s = mb_str_split($term, 1, 'UTF-8');
        $length = \count($s);

        for ($i = 0; $i < $length; ++$i) {
            $c = $s[$i];

            if ('ä' === $c) {
                $s[$i] = 'æ';
            } elseif ('Ä' === $c) {
                $s[$i] = 'Æ';
            } elseif ('ö' === $c) {
                $s[$i] = 'ø';
            } elseif ('Ö' === $c) {
                $s[$i] = 'Ø';
            } elseif ($length - 1 > $i) {
                $next = $s[$i + 1];
                $replacement = match (true) {
                    'a' === $c && \in_array($next, ['a', 'A', 'o', 'O'], true) => 'å',
                    'A' === $c && \in_array($next, ['a', 'A', 'o', 'O'], true) => 'Å',
                    'a' === $c && ('e' === $next || 'E' === $next) => 'æ',
                    'A' === $c && ('e' === $next || 'E' === $next) => 'Æ',
                    'o' === $c && \in_array($next, ['e', 'E', 'o', 'O'], true) => 'ø',
                    'O' === $c && \in_array($next, ['e', 'E', 'o', 'O'], true) => 'Ø',
                    default => null,
                };

                if (null !== $replacement) {
                    array_splice($s, $i + 1, 1);
                    --$length;
                    $s[$i] = $replacement;
                }
            }
        }

        return implode('', $s);
    }
}
