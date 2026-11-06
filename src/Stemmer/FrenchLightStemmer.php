<?php

/*
 * Port of Apache Lucene's FrenchLightStemmer
 * (org.apache.lucene.analysis.fr.FrenchLightStemmer), the stemmer used by
 * Elasticsearch's `light_french` stemmer and `french` analyzer.
 *
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements. The ASF licenses this file to You under the
 * Apache License, Version 2.0 (the "License"); you may not use this file
 * except in compliance with the License. You may obtain a copy of the License
 * at https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * The algorithm is the "UniNE" light stemmer described in "Light Stemming
 * Approaches for the French, Portuguese, German and Hungarian Languages",
 * Jacques Savoy. Original code: Copyright (c) 2005, Jacques Savoy. All rights
 * reserved. Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following conditions
 * are met: redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer;
 * redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution; neither the name of
 * the author nor the names of its contributors may be used to endorse or
 * promote products derived from this software without specific prior written
 * permission. THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND
 * CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES ARE DISCLAIMED.
 */

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

final class FrenchLightStemmer implements StemmerInterface
{
    private const ACCENTS = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ô' => 'o', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ù' => 'u', 'û' => 'u', 'î' => 'i', 'ç' => 'c'];

    public function stem(string $word): string
    {
        $s = mb_str_split($word, 1, 'UTF-8');
        $len = $this->doStem($s, \count($s));

        return implode('', \array_slice($s, 0, $len));
    }

    /**
     * @param list<string> $s
     */
    private function doStem(array &$s, int $len): int
    {
        if ($len > 5 && 'x' === $s[$len - 1]) {
            if ('a' === $s[$len - 3] && 'u' === $s[$len - 2] && 'e' !== $s[$len - 4]) {
                $s[$len - 2] = 'l';
            }
            --$len;
        }

        if ($len > 3 && 'x' === $s[$len - 1]) {
            --$len;
        }

        if ($len > 3 && 's' === $s[$len - 1]) {
            --$len;
        }

        if ($len > 9 && self::endsWith($s, $len, 'issement')) {
            $len -= 6;
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 8 && self::endsWith($s, $len, 'issant')) {
            $len -= 4;
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 6 && self::endsWith($s, $len, 'ement')) {
            $len -= 4;
            if ($len > 3 && self::endsWith($s, $len, 'ive')) {
                --$len;
                $s[$len - 1] = 'f';
            }

            return $this->norm($s, $len);
        }

        if ($len > 11 && self::endsWith($s, $len, 'ficatrice')) {
            $len -= 5;
            $s[$len - 2] = 'e';
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 10 && self::endsWith($s, $len, 'ficateur')) {
            $len -= 4;
            $s[$len - 2] = 'e';
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 9 && self::endsWith($s, $len, 'catrice')) {
            $len -= 3;
            $s[$len - 4] = 'q';
            $s[$len - 3] = 'u';
            $s[$len - 2] = 'e';

            return $this->norm($s, $len);
        }

        if ($len > 8 && self::endsWith($s, $len, 'cateur')) {
            $len -= 2;
            $s[$len - 4] = 'q';
            $s[$len - 3] = 'u';
            $s[$len - 2] = 'e';
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 8 && self::endsWith($s, $len, 'atrice')) {
            $len -= 4;
            $s[$len - 2] = 'e';
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 7 && self::endsWith($s, $len, 'ateur')) {
            $len -= 3;
            $s[$len - 2] = 'e';
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 6 && self::endsWith($s, $len, 'trice')) {
            --$len;
            $s[$len - 3] = 'e';
            $s[$len - 2] = 'u';
            $s[$len - 1] = 'r';
        }

        if ($len > 5 && self::endsWith($s, $len, 'ième')) {
            return $this->norm($s, $len - 4);
        }

        if ($len > 7 && self::endsWith($s, $len, 'teuse')) {
            $len -= 2;
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 6 && self::endsWith($s, $len, 'teur')) {
            --$len;
            $s[$len - 1] = 'r';

            return $this->norm($s, $len);
        }

        if ($len > 5 && self::endsWith($s, $len, 'euse')) {
            return $this->norm($s, $len - 2);
        }

        if ($len > 8 && self::endsWith($s, $len, 'ère')) {
            --$len;
            $s[$len - 2] = 'e';

            return $this->norm($s, $len);
        }

        if ($len > 7 && self::endsWith($s, $len, 'ive')) {
            --$len;
            $s[$len - 1] = 'f';

            return $this->norm($s, $len);
        }

        if ($len > 4 && (self::endsWith($s, $len, 'folle') || self::endsWith($s, $len, 'molle'))) {
            $len -= 2;
            $s[$len - 1] = 'u';

            return $this->norm($s, $len);
        }

        if ($len > 9 && self::endsWith($s, $len, 'nnelle')) {
            return $this->norm($s, $len - 5);
        }

        if ($len > 9 && self::endsWith($s, $len, 'nnel')) {
            return $this->norm($s, $len - 3);
        }

        if ($len > 4 && self::endsWith($s, $len, 'ète')) {
            --$len;
            $s[$len - 2] = 'e';
        }

        if ($len > 8 && self::endsWith($s, $len, 'ique')) {
            $len -= 4;
        }

        if ($len > 8 && self::endsWith($s, $len, 'esse')) {
            return $this->norm($s, $len - 3);
        }

        if ($len > 7 && self::endsWith($s, $len, 'inage')) {
            return $this->norm($s, $len - 3);
        }

        if ($len > 9 && self::endsWith($s, $len, 'isation')) {
            $len -= 7;
            if ($len > 5 && self::endsWith($s, $len, 'ual')) {
                $s[$len - 2] = 'e';
            }

            return $this->norm($s, $len);
        }

        if ($len > 9 && self::endsWith($s, $len, 'isateur')) {
            return $this->norm($s, $len - 7);
        }

        if ($len > 8 && self::endsWith($s, $len, 'ation')) {
            return $this->norm($s, $len - 5);
        }

        if ($len > 8 && self::endsWith($s, $len, 'ition')) {
            return $this->norm($s, $len - 5);
        }

        return $this->norm($s, $len);
    }

    /**
     * @param list<string> $s
     */
    private function norm(array &$s, int $len): int
    {
        if ($len > 4) {
            for ($i = 0; $i < $len; ++$i) {
                $s[$i] = self::ACCENTS[$s[$i]] ?? $s[$i];
            }

            $ch = $s[0];
            for ($i = 1; $i < $len; ++$i) {
                if ($s[$i] === $ch && self::isLetter($ch)) {
                    array_splice($s, $i, 1);
                    --$len;
                    --$i;
                } else {
                    $ch = $s[$i];
                }
            }
        }

        if ($len > 4 && self::endsWith($s, $len, 'ie')) {
            $len -= 2;
        }

        if ($len > 4) {
            if ('r' === $s[$len - 1]) {
                --$len;
            }
            if ('e' === $s[$len - 1]) {
                --$len;
            }
            if ('e' === $s[$len - 1]) {
                --$len;
            }
            if ($s[$len - 1] === $s[$len - 2] && self::isLetter($s[$len - 1])) {
                --$len;
            }
        }

        return $len;
    }

    /**
     * @param list<string> $s
     */
    private static function endsWith(array $s, int $len, string $suffix): bool
    {
        $suffix = mb_str_split($suffix, 1, 'UTF-8');
        $count = \count($suffix);

        if ($count > $len) {
            return false;
        }

        for ($i = 0; $i < $count; ++$i) {
            if ($s[$len - $count + $i] !== $suffix[$i]) {
                return false;
            }
        }

        return true;
    }

    private static function isLetter(string $char): bool
    {
        return 1 === preg_match('/^\p{L}$/u', $char);
    }
}
