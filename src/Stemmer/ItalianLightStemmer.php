<?php

/*
 * Port of Apache Lucene's ItalianLightStemmer (org.apache.lucene.analysis.it), used by
 * Elasticsearch's `light_italian` stemmer and `italian` analyzer.
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

final class ItalianLightStemmer implements StemmerInterface
{
    private const ACCENTS = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i'];

    public function stem(string $word): string
    {
        $s = mb_str_split($word, 1, 'UTF-8');
        $len = \count($s);
        if ($len < 6) {
            return $word;
        }

        $s = array_map(static fn (string $c): string => self::ACCENTS[$c] ?? $c, $s);
        $previous = $s[$len - 2];

        $len = match ($s[$len - 1]) {
            'e' => 'i' === $previous || 'h' === $previous ? $len - 2 : $len - 1,
            'i' => 'h' === $previous || 'i' === $previous ? $len - 2 : $len - 1,
            'a', 'o' => 'i' === $previous ? $len - 2 : $len - 1,
            default => $len,
        };

        return implode('', \array_slice($s, 0, $len));
    }
}
