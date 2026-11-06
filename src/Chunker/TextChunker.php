<?php

declare(strict_types=1);

namespace TokenizerEngine\Chunker;

use TokenizerEngine\TokenizerEngine;

/**
 * Splits a text into passages of at most $maxSize, cutting at sentence
 * boundaries whenever possible (then at word boundaries, then anywhere), with
 * an optional overlap between consecutive passages. Typical use: preparing
 * documents for embeddings / retrieval augmented generation (RAG).
 *
 * The size is measured in characters by default; pass a $sizer to count
 * anything else (words, LLM tokens...), or use byTokens().
 *
 * Not an Elasticsearch component.
 */
final class TextChunker
{
    private const FALLBACK_SENTENCE = '/.*?(?:[.!?…]+["»”’)\]]*(?:\s+|$)|\R+|$)/su';

    /**
     * @var \Closure(string): int
     */
    private readonly \Closure $sizer;

    /**
     * @param (callable(string): int)|null $sizer
     */
    public function __construct(
        private readonly int $maxSize = 1000,
        private readonly int $overlap = 0,
        ?callable $sizer = null,
        private readonly string $locale = 'root',
        private readonly bool $useIntl = true,
    ) {
        if ($maxSize < 1) {
            throw new \InvalidArgumentException('maxSize must be greater than 0.');
        }

        if ($overlap < 0 || $overlap >= $maxSize) {
            throw new \InvalidArgumentException('overlap must be between 0 and maxSize - 1.');
        }

        $this->sizer = null === $sizer ? static fn (string $text): int => mb_strlen($text, 'UTF-8') : $sizer(...);
    }

    /**
     * Measures sizes in tokens produced by an analyzer.
     */
    public static function byTokens(TokenizerEngine $analyzer, int $maxTokens, int $overlapTokens = 0, string $locale = 'root'): self
    {
        return new self($maxTokens, $overlapTokens, static fn (string $text): int => \count($analyzer->tokenize($text)), $locale);
    }

    /**
     * @return list<Chunk>
     */
    public function chunk(string $text): array
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        $units = [];
        foreach ($this->sentences($text) as $sentence) {
            array_push($units, ...$this->fit($sentence));
        }

        $chunks = [];
        $current = [];

        foreach ($units as $unit) {
            if ([] !== $current && $this->size([...$current, $unit]) > $this->maxSize) {
                $chunks[] = $current;
                $current = $this->overlapUnits($current);

                while ([] !== $current && $this->size([...$current, $unit]) > $this->maxSize) {
                    array_shift($current);
                }
            }

            $current[] = $unit;
        }

        if ([] !== $current) {
            $chunks[] = $current;
        }

        $result = [];
        foreach ($chunks as $chunk) {
            $made = $this->make($chunk, \count($result));
            if (null !== $made) {
                $result[] = $made;
            }
        }

        return $result;
    }

    /**
     * Contiguous sentences covering the whole text.
     *
     * @return list<array{0: string, 1: int}> text, character offset
     */
    private function sentences(string $text): array
    {
        if ('' === $text) {
            return [];
        }

        $byteMatches = [];

        if ($this->useIntl && class_exists(\IntlBreakIterator::class) && null !== ($iterator = \IntlBreakIterator::createSentenceInstance($this->locale))) {
            $iterator->setText($text);
            $start = $iterator->first();
            for ($end = $iterator->next(); \IntlBreakIterator::DONE !== $end; $start = $end, $end = $iterator->next()) {
                $byteMatches[] = [substr($text, $start, $end - $start), $start];
            }
        } else {
            preg_match_all(self::FALLBACK_SENTENCE, $text, $matches, \PREG_OFFSET_CAPTURE);
            $byteMatches = array_values(array_filter($matches[0], static fn (array $m): bool => '' !== $m[0]));
        }

        $sentences = [];
        $char = 0;
        foreach ($byteMatches as [$sentence]) {
            $sentences[] = [$sentence, $char];
            $char += mb_strlen($sentence, 'UTF-8');
        }

        return $sentences;
    }

    /**
     * Splits a unit that is too large: words first, then characters.
     *
     * @param array{0: string, 1: int} $unit
     *
     * @return list<array{0: string, 1: int}>
     */
    private function fit(array $unit, bool $words = true): array
    {
        if (($this->sizer)($unit[0]) <= $this->maxSize) {
            return [$unit];
        }

        $pieces = $words
            ? (preg_split('/(?<=\s)(?=\S)/u', $unit[0]) ?: [$unit[0]])
            : mb_str_split($unit[0], 1, 'UTF-8');

        if (1 === \count($pieces)) {
            return $words ? $this->fit($unit, false) : [$unit];
        }

        // Re-group pieces greedily so that each group fits.
        $result = [];
        $group = '';
        $groupStart = $unit[1];
        $offset = $unit[1];

        foreach ($pieces as $piece) {
            if ('' !== $group && ($this->sizer)($group.$piece) > $this->maxSize) {
                array_push($result, ...($words ? $this->fit([$group, $groupStart], false) : [[$group, $groupStart]]));
                $group = '';
                $groupStart = $offset;
            }
            $group .= $piece;
            $offset += mb_strlen($piece, 'UTF-8');
        }

        if ('' !== $group) {
            array_push($result, ...($words ? $this->fit([$group, $groupStart], false) : [[$group, $groupStart]]));
        }

        return $result;
    }

    /**
     * Trailing units of the previous chunk reused at the beginning of the
     * next one (never the whole chunk, so that the text always progresses).
     *
     * @param list<array{0: string, 1: int}> $units
     *
     * @return list<array{0: string, 1: int}>
     */
    private function overlapUnits(array $units): array
    {
        if (0 === $this->overlap) {
            return [];
        }

        $kept = [];
        for ($i = \count($units) - 1; $i > 0; --$i) {
            $candidate = [$units[$i], ...$kept];
            if ($this->size($candidate) > $this->overlap) {
                break;
            }
            $kept = $candidate;
        }

        return $kept;
    }

    /**
     * @param list<array{0: string, 1: int}> $units
     */
    private function size(array $units): int
    {
        return ($this->sizer)(trim(implode('', array_column($units, 0))));
    }

    /**
     * @param list<array{0: string, 1: int}> $units
     */
    private function make(array $units, int $index): ?Chunk
    {
        $raw = implode('', array_column($units, 0));
        $text = trim($raw);
        if ('' === $text) {
            return null;
        }

        $leading = mb_strlen($raw, 'UTF-8') - mb_strlen(ltrim($raw), 'UTF-8');
        $start = $units[0][1] + $leading;

        return new Chunk($text, $start, $start + mb_strlen($text, 'UTF-8'), $index, ($this->sizer)($text));
    }
}
