<?php

/*
 * Port of Apache Lucene's PortugueseLightStemmer (org.apache.lucene.analysis.pt), used by
 * Elasticsearch's `light_portuguese` stemmer and `portuguese` analyzer.
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

final class PortugueseLightStemmer implements StemmerInterface
{
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ç' => 'c',
    ];

    public function stem(string $word): string
    {
        $s = mb_str_split($word, 1, 'UTF-8');
        $len = \count($s);
        if ($len < 4) {
            return $word;
        }

        $len = $this->removeSuffix($s, $len);

        if ($len > 3 && 'a' === $s[$len - 1]) {
            $len = $this->normFeminine($s, $len);
        }

        if ($len > 4 && \in_array($s[$len - 1], ['e', 'a', 'o'], true)) {
            --$len;
        }

        $s = array_map(static fn (string $c): string => self::ACCENTS[$c] ?? $c, \array_slice($s, 0, $len));

        return implode('', $s);
    }

    /**
     * @param array<int, string> $s
     */
    private function removeSuffix(array &$s, int $len): int
    {
        if ($len > 4 && self::endsWith($s, $len, 'es') && \in_array($s[$len - 3], ['r', 's', 'l', 'z'], true)) {
            return $len - 2;
        }

        if ($len > 3 && self::endsWith($s, $len, 'ns')) {
            $s[$len - 2] = 'm';

            return $len - 1;
        }

        if ($len > 4 && (self::endsWith($s, $len, 'eis') || self::endsWith($s, $len, 'éis'))) {
            $s[$len - 3] = 'e';
            $s[$len - 2] = 'l';

            return $len - 1;
        }

        if ($len > 4 && self::endsWith($s, $len, 'ais')) {
            $s[$len - 2] = 'l';

            return $len - 1;
        }

        if ($len > 4 && self::endsWith($s, $len, 'óis')) {
            $s[$len - 3] = 'o';
            $s[$len - 2] = 'l';

            return $len - 1;
        }

        if ($len > 4 && self::endsWith($s, $len, 'is')) {
            $s[$len - 1] = 'l';

            return $len;
        }

        if ($len > 3 && (self::endsWith($s, $len, 'ões') || self::endsWith($s, $len, 'ães'))) {
            --$len;
            $s[$len - 2] = 'ã';
            $s[$len - 1] = 'o';

            return $len;
        }

        if ($len > 6 && self::endsWith($s, $len, 'mente')) {
            return $len - 5;
        }

        if ($len > 3 && 's' === $s[$len - 1]) {
            return $len - 1;
        }

        return $len;
    }

    /**
     * @param array<int, string> $s
     */
    private function normFeminine(array &$s, int $len): int
    {
        if ($len > 7 && (self::endsWith($s, $len, 'inha') || self::endsWith($s, $len, 'iaca') || self::endsWith($s, $len, 'eira'))) {
            $s[$len - 1] = 'o';

            return $len;
        }

        if ($len > 6) {
            foreach (['osa', 'ica', 'ida', 'ada', 'iva', 'ama'] as $suffix) {
                if (self::endsWith($s, $len, $suffix)) {
                    $s[$len - 1] = 'o';

                    return $len;
                }
            }

            if (self::endsWith($s, $len, 'ona')) {
                $s[$len - 3] = 'ã';
                $s[$len - 2] = 'o';

                return $len - 1;
            }

            if (self::endsWith($s, $len, 'ora')) {
                return $len - 1;
            }

            if (self::endsWith($s, $len, 'esa')) {
                $s[$len - 3] = 'ê';

                return $len - 1;
            }

            if (self::endsWith($s, $len, 'na')) {
                $s[$len - 1] = 'o';

                return $len;
            }
        }

        return $len;
    }

    /**
     * @param array<int, string> $s
     */
    private static function endsWith(array $s, int $len, string $suffix): bool
    {
        $chars = mb_str_split($suffix, 1, 'UTF-8');
        $count = \count($chars);

        return $count <= $len && \array_slice($s, $len - $count, $count) === $chars;
    }
}
