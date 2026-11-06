<?php

/*
 * Port of the hyphenation of Apache Lucene (org.apache.lucene.analysis.compound
 * .hyphenation: HyphenationTree, PatternParser), itself derived from Apache
 * FOP. Licensed to the Apache Software Foundation (ASF) under the Apache
 * License, Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0).
 * Distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND.
 */

declare(strict_types=1);

namespace TokenizerEngine\Hyphenation;

/**
 * Hyphenation with Liang's algorithm (as in TeX), loaded from a pattern file
 * in the XML format of Apache FOP, the format Elasticsearch's
 * `hyphenation_decompounder` reads:
 *
 *   <hyphenation-info>
 *     <hyphen-char value="-"/>
 *     <classes>aA bB cC ...</classes>          letters and their equivalents
 *     <exceptions>ta-ble ...</exceptions>      explicit hyphenations
 *     <patterns>.ach4 a1b ...</patterns>       Liang patterns
 *   </hyphenation-info>
 *
 * Pattern files are available for many languages (OFFO project, LaTeX); check
 * their license before distributing them.
 */
final class HyphenationTree
{
    /**
     * @var array<string, string> character => its class (equivalent lowercase letter)
     */
    private array $classes = [];

    /**
     * @var array<string, list<int>> pattern letters => interletter values
     */
    private array $patterns = [];

    private int $maxPatternLength = 0;

    /**
     * @var array<string, list<int>> word => hyphenation points
     */
    private array $exceptions = [];

    public static function fromFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException(\sprintf('Hyphenation patterns file "%s" is not readable.', $path));
        }

        return self::fromXml((string) file_get_contents($path));
    }

    public static function fromXml(string $xml): self
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \LogicException('Reading hyphenation patterns requires the dom extension.');
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($xml, \LIBXML_NONET)) {
            throw new \InvalidArgumentException('Invalid hyphenation patterns XML.');
        }

        $tree = new self();
        $hyphenChar = '-';
        foreach ($document->getElementsByTagName('hyphen-char') as $element) {
            $value = $element->getAttribute('value');
            $first = mb_substr($value, 0, 1);
            if ('' !== $first) {
                $hyphenChar = $first;
            }
        }

        foreach ($document->getElementsByTagName('classes') as $element) {
            foreach (self::words($element->textContent) as $group) {
                $tree->addClass($group);
            }
        }

        foreach ($document->getElementsByTagName('exceptions') as $element) {
            foreach (self::exceptionWords($element, $hyphenChar) as $parts) {
                $tree->addException($parts);
            }
        }

        foreach ($document->getElementsByTagName('patterns') as $element) {
            foreach (self::words($element->textContent) as $pattern) {
                $tree->addPattern($pattern);
            }
        }

        return $tree;
    }

    /**
     * Hyphenation points of $word (character offsets, 0 and the word length
     * included), or null when it cannot be hyphenated.
     *
     * @return list<int>|null
     */
    public function hyphenate(string $word, int $remainCharCount = 1, int $pushCharCount = 1): ?array
    {
        $chars = mb_str_split($word, 1, 'UTF-8');
        $letters = [];
        $ignoreAtBeginning = 0;
        $endOfLetters = false;

        // Normalize the word: map letters to their class, skip leading
        // non-letters, give up on a letter after trailing non-letters.
        foreach ($chars as $i => $char) {
            $class = $this->classes[$char] ?? null;
            if (null === $class) {
                if ($i === $ignoreAtBeginning) {
                    ++$ignoreAtBeginning;
                } else {
                    $endOfLetters = true;
                }
                continue;
            }
            if ($endOfLetters) {
                return null;
            }
            $letters[] = $class;
        }

        $length = \count($letters);
        if ($length < $remainCharCount + $pushCharCount) {
            return null;
        }

        $points = [];
        $exception = $this->exceptions[implode('', $letters)] ?? null;

        if (null !== $exception) {
            foreach ($exception as $j) {
                if ($j >= $remainCharCount && $j < $length - $pushCharCount) {
                    $points[] = $j + $ignoreAtBeginning;
                }
            }
        } else {
            $word = ['.', ...$letters, '.'];
            $values = array_fill(0, $length + 3, 0);

            for ($i = 0; $i < $length + 1; ++$i) {
                $this->searchPatterns($word, $i, $values);
            }

            // Hyphenation points are where the interletter value is odd.
            for ($i = 0; $i < $length; ++$i) {
                if (1 === ($values[$i + 1] & 1) && $i >= $remainCharCount && $i <= $length - $pushCharCount) {
                    $points[] = $i + $ignoreAtBeginning;
                }
            }
        }

        return [] === $points ? null : [0, ...$points, $length];
    }

    /**
     * @param list<string> $word
     * @param list<int>    $values
     */
    private function searchPatterns(array $word, int $index, array &$values): void
    {
        $available = \count($word) - $index;
        $key = '';

        for ($length = 1; $length <= $this->maxPatternLength && $length <= $available; ++$length) {
            $key .= $word[$index + $length - 1];
            $pattern = $this->patterns[$key] ?? null;
            if (null === $pattern) {
                continue;
            }

            foreach ($pattern as $k => $value) {
                $j = $index + $k;
                if ($j < \count($values) && $value > $values[$j]) {
                    $values[$j] = $value;
                }
            }
        }
    }

    private function addClass(string $group): void
    {
        $chars = mb_str_split($group, 1, 'UTF-8');
        foreach ($chars as $char) {
            $this->classes[$char] = $chars[0];
        }
    }

    /**
     * @param list<string> $parts the word split at its hyphenation points
     */
    private function addException(array $parts): void
    {
        $points = [];
        $offset = 0;
        foreach ($parts as $part) {
            $offset += mb_strlen($part, 'UTF-8');
            $points[] = $offset;
        }

        $this->exceptions[implode('', $parts)] = $points;
    }

    private function addPattern(string $pattern): void
    {
        // "a1b" gives the letters "ab" and the interletter values [0, 1, 0].
        $letters = '';
        $values = [];
        $chars = [...mb_str_split($pattern, 1, 'UTF-8'), 'a'];
        $count = \count($chars);

        for ($i = 0; $i < $count; ++$i) {
            if (ctype_digit($chars[$i])) {
                $values[] = (int) $chars[$i];
                ++$i;
            } else {
                $values[] = 0;
            }
            if ($i < $count - 1) {
                $letters .= $chars[$i];
            }
        }

        $this->patterns[$letters] = $values;
        $this->maxPatternLength = max($this->maxPatternLength, mb_strlen($letters, 'UTF-8'));
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Exceptions are words with hyphen characters or <hyphen/> elements.
     *
     * @param non-empty-string $hyphenChar
     *
     * @return list<list<string>>
     */
    private static function exceptionWords(\DOMElement $element, string $hyphenChar): array
    {
        $text = '';
        foreach ($element->childNodes as $node) {
            $text .= $node instanceof \DOMElement && 'hyphen' === $node->nodeName ? $hyphenChar : $node->textContent;
        }

        return array_map(
            static fn (string $word): array => array_values(array_filter(explode($hyphenChar, $word), static fn (string $p): bool => '' !== $p)),
            self::words($text),
        );
    }
}
