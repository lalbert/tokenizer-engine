<?php

/*
 * Port of Apache Lucene's PorterStemmer (org.apache.lucene.analysis.en),
 * used by Elasticsearch's `porter_stem` filter, the `english`/`porter`
 * stemmers and the `english` analyzer.
 *
 * Licensed to the Apache Software Foundation (ASF) under the Apache License,
 * Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0). Distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND.
 *
 * Algorithm: M.F. Porter, 1980, "An algorithm for suffix stripping",
 * Program, Vol. 14, no. 3, pp 130-137.
 */

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

final class PorterStemmer implements StemmerInterface
{
    /**
     * @var array<int, string>
     */
    private array $b = [];

    private int $j = 0;
    private int $k = 0;

    public function stem(string $word): string
    {
        $this->b = mb_str_split($word, 1, 'UTF-8');
        $this->k = \count($this->b) - 1;

        if ($this->k > 1) {
            $this->step1();
            $this->step2();
            $this->step3();
            $this->step4();
            $this->step5();
            $this->step6();
        }

        return implode('', \array_slice($this->b, 0, $this->k + 1));
    }

    private function cons(int $i): bool
    {
        return match ($this->b[$i]) {
            'a', 'e', 'i', 'o', 'u' => false,
            'y' => 0 === $i || !$this->cons($i - 1),
            default => true,
        };
    }

    /**
     * Number of consonant sequences between 0 and j.
     */
    private function m(): int
    {
        $n = 0;
        $i = 0;
        while (true) {
            if ($i > $this->j) {
                return $n;
            }
            if (!$this->cons($i)) {
                break;
            }
            ++$i;
        }
        ++$i;
        while (true) {
            while (true) {
                if ($i > $this->j) {
                    return $n;
                }
                if ($this->cons($i)) {
                    break;
                }
                ++$i;
            }
            ++$i;
            ++$n;
            while (true) {
                if ($i > $this->j) {
                    return $n;
                }
                if (!$this->cons($i)) {
                    break;
                }
                ++$i;
            }
            ++$i;
        }
    }

    private function vowelInStem(): bool
    {
        for ($i = 0; $i <= $this->j; ++$i) {
            if (!$this->cons($i)) {
                return true;
            }
        }

        return false;
    }

    private function doublec(int $j): bool
    {
        return $j >= 1 && $this->b[$j] === $this->b[$j - 1] && $this->cons($j);
    }

    private function cvc(int $i): bool
    {
        if ($i < 2 || !$this->cons($i) || $this->cons($i - 1) || !$this->cons($i - 2)) {
            return false;
        }

        return !\in_array($this->b[$i], ['w', 'x', 'y'], true);
    }

    private function ends(string $s): bool
    {
        $length = \strlen($s);
        $o = $this->k - $length + 1;
        if ($o < 0) {
            return false;
        }

        for ($i = 0; $i < $length; ++$i) {
            if ($this->b[$o + $i] !== $s[$i]) {
                return false;
            }
        }
        $this->j = $this->k - $length;

        return true;
    }

    private function setTo(string $s): void
    {
        $length = \strlen($s);
        for ($i = 0; $i < $length; ++$i) {
            $this->b[$this->j + 1 + $i] = $s[$i];
        }
        $this->k = $this->j + $length;
    }

    private function r(string $s): void
    {
        if ($this->m() > 0) {
            $this->setTo($s);
        }
    }

    private function step1(): void
    {
        if ('s' === $this->b[$this->k]) {
            if ($this->ends('sses')) {
                $this->k -= 2;
            } elseif ($this->ends('ies')) {
                $this->setTo('i');
            } elseif ('s' !== $this->b[$this->k - 1]) {
                --$this->k;
            }
        }

        if ($this->ends('eed')) {
            if ($this->m() > 0) {
                --$this->k;
            }
        } elseif (($this->ends('ed') || $this->ends('ing')) && $this->vowelInStem()) {
            $this->k = $this->j;
            if ($this->ends('at')) {
                $this->setTo('ate');
            } elseif ($this->ends('bl')) {
                $this->setTo('ble');
            } elseif ($this->ends('iz')) {
                $this->setTo('ize');
            } elseif ($this->doublec($this->k)) {
                $ch = $this->b[$this->k--];
                if ('l' === $ch || 's' === $ch || 'z' === $ch) {
                    ++$this->k;
                }
            } elseif (1 === $this->m() && $this->cvc($this->k)) {
                $this->setTo('e');
            }
        }
    }

    private function step2(): void
    {
        if ($this->ends('y') && $this->vowelInStem()) {
            $this->b[$this->k] = 'i';
        }
    }

    private function step3(): void
    {
        if (0 === $this->k) {
            return;
        }

        $rules = match ($this->b[$this->k - 1]) {
            'a' => ['ational' => 'ate', 'tional' => 'tion'],
            'c' => ['enci' => 'ence', 'anci' => 'ance'],
            'e' => ['izer' => 'ize'],
            'l' => ['bli' => 'ble', 'alli' => 'al', 'entli' => 'ent', 'eli' => 'e', 'ousli' => 'ous'],
            'o' => ['ization' => 'ize', 'ation' => 'ate', 'ator' => 'ate'],
            's' => ['alism' => 'al', 'iveness' => 'ive', 'fulness' => 'ful', 'ousness' => 'ous'],
            't' => ['aliti' => 'al', 'iviti' => 'ive', 'biliti' => 'ble'],
            'g' => ['logi' => 'log'],
            default => [],
        };

        $this->replaceFirst($rules);
    }

    private function step4(): void
    {
        $rules = match ($this->b[$this->k]) {
            'e' => ['icate' => 'ic', 'ative' => '', 'alize' => 'al'],
            'i' => ['iciti' => 'ic'],
            'l' => ['ical' => 'ic', 'ful' => ''],
            's' => ['ness' => ''],
            default => [],
        };

        $this->replaceFirst($rules);
    }

    /**
     * @param array<string, string> $rules
     */
    private function replaceFirst(array $rules): void
    {
        foreach ($rules as $suffix => $replacement) {
            if ($this->ends($suffix)) {
                $this->r($replacement);

                return;
            }
        }
    }

    private function step5(): void
    {
        if (0 === $this->k) {
            return;
        }

        $suffixes = match ($this->b[$this->k - 1]) {
            'a' => ['al'],
            'c' => ['ance', 'ence'],
            'e' => ['er'],
            'i' => ['ic'],
            'l' => ['able', 'ible'],
            'n' => ['ant', 'ement', 'ment', 'ent'],
            'o' => ['ion', 'ou'],
            's' => ['ism'],
            't' => ['ate', 'iti'],
            'u' => ['ous'],
            'v' => ['ive'],
            'z' => ['ize'],
            default => [],
        };

        foreach ($suffixes as $suffix) {
            if (!$this->ends($suffix)) {
                continue;
            }
            if ('ion' === $suffix && !($this->j >= 0 && ('s' === $this->b[$this->j] || 't' === $this->b[$this->j]))) {
                continue;
            }
            if ($this->m() > 1) {
                $this->k = $this->j;
            }

            return;
        }
    }

    private function step6(): void
    {
        $this->j = $this->k;

        if ('e' === $this->b[$this->k]) {
            $a = $this->m();
            if ($a > 1 || (1 === $a && !$this->cvc($this->k - 1))) {
                --$this->k;
            }
        }

        if ('l' === $this->b[$this->k] && $this->doublec($this->k) && $this->m() > 1) {
            --$this->k;
        }
    }
}
