<?php

/*
 * Port of Apache Lucene's GermanLightStemmer (org.apache.lucene.analysis.de), used by
 * Elasticsearch's `light_german` stemmer and `german` analyzer.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 *
 * Algorithm from "Light Stemming Approaches for the French, Portuguese,
 * German and Hungarian Languages" (and related papers), Jacques Savoy.
 * Original code: Copyright (c) 2005, Jacques Savoy, BSD license (see
 * FrenchLightStemmer.php for the full notice).
 */

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

final class GermanLightStemmer implements StemmerInterface
{
    private const ACCENTS = [
        'ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a',
        'ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
        'ï' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i',
        'ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u',
    ];

    public function stem(string $word): string
    {
        $s = array_map(static fn (string $c): string => self::ACCENTS[$c] ?? $c, mb_str_split($word, 1, 'UTF-8'));
        $len = $this->step2($s, $this->step1($s, \count($s)));

        return implode('', \array_slice($s, 0, $len));
    }

    private static function stEnding(string $ch): bool
    {
        return \in_array($ch, ['b', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 't'], true);
    }

    /**
     * @param array<int, string> $s
     */
    private function step1(array $s, int $len): int
    {
        if ($len > 5 && 'e' === $s[$len - 3] && 'r' === $s[$len - 2] && 'n' === $s[$len - 1]) {
            return $len - 3;
        }
        if ($len > 4 && 'e' === $s[$len - 2] && \in_array($s[$len - 1], ['m', 'n', 'r', 's'], true)) {
            return $len - 2;
        }
        if ($len > 3 && 'e' === $s[$len - 1]) {
            return $len - 1;
        }
        if ($len > 3 && 's' === $s[$len - 1] && self::stEnding($s[$len - 2])) {
            return $len - 1;
        }

        return $len;
    }

    /**
     * @param array<int, string> $s
     */
    private function step2(array $s, int $len): int
    {
        if ($len > 5 && 'e' === $s[$len - 3] && 's' === $s[$len - 2] && 't' === $s[$len - 1]) {
            return $len - 3;
        }
        if ($len > 4 && 'e' === $s[$len - 2] && ('r' === $s[$len - 1] || 'n' === $s[$len - 1])) {
            return $len - 2;
        }
        if ($len > 4 && 's' === $s[$len - 2] && 't' === $s[$len - 1] && self::stEnding($s[$len - 3])) {
            return $len - 2;
        }

        return $len;
    }
}
