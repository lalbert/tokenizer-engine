<?php

/*
 * Port of Apache Lucene's SpanishLightStemmer (org.apache.lucene.analysis.es), used by
 * Elasticsearch's `light_spanish` stemmer and `spanish` analyzer.
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

final class SpanishLightStemmer implements StemmerInterface
{
    private const ACCENTS = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i'];

    public function stem(string $word): string
    {
        $s = mb_str_split($word, 1, 'UTF-8');
        $len = \count($s);
        if ($len < 5) {
            return $word;
        }

        $s = array_map(static fn (string $c): string => self::ACCENTS[$c] ?? $c, $s);

        $len = match ($s[$len - 1]) {
            'o', 'a', 'e' => $len - 1,
            's' => self::plural($s, $len),
            default => $len,
        };

        return implode('', \array_slice($s, 0, $len));
    }

    /**
     * @param array<int, string> $s
     */
    private static function plural(array &$s, int $len): int
    {
        if ('e' === $s[$len - 2] && 's' === $s[$len - 3] && 'e' === $s[$len - 4]) {
            return $len - 2;
        }
        if ('e' === $s[$len - 2] && 'c' === $s[$len - 3]) {
            $s[$len - 3] = 'z';

            return $len - 2;
        }
        if (\in_array($s[$len - 2], ['o', 'a', 'e'], true)) {
            return $len - 2;
        }

        return $len;
    }
}
