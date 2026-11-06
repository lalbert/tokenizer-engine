<?php

declare(strict_types=1);

namespace TokenizerEngine;

/**
 * Maps offsets of a character-filtered text back to the original text, like
 * Lucene's offset corrections (BaseCharFilter): an offset o of the filtered
 * text becomes o + d, where d is the cumulative length difference recorded at
 * the last correction point <= o. Also remembers the markup removed by the
 * character filters (HTML tags...), in original coordinates.
 *
 * Offsets are counted in Unicode code points.
 */
final class OffsetMap
{
    /**
     * @param list<int>                   $points filtered offsets of the correction points, ascending
     * @param list<int>                   $diffs  cumulative difference at each point
     * @param list<array{0: int, 1: int}> $markup removed markup, [start, end) in original coordinates
     */
    private function __construct(
        private readonly array $points,
        private readonly array $diffs,
        private readonly array $markup,
        private readonly ?self $inner = null,
    ) {
    }

    public static function identity(): self
    {
        return new self([], [], []);
    }

    /**
     * @param array<int, int>             $corrections filtered offset => cumulative difference
     * @param list<array{0: int, 1: int}> $markup      removed markup in the coordinates of the filter input
     */
    public static function fromCorrections(array $corrections, array $markup = []): self
    {
        ksort($corrections);

        return new self(array_keys($corrections), array_values($corrections), $markup);
    }

    /**
     * Composes with the map of a character filter applied after this one.
     */
    public function then(self $next): self
    {
        if ($next->isIdentity()) {
            return $this;
        }
        if ($this->isIdentity()) {
            return $next;
        }

        $markup = $this->markup;
        foreach ($next->markup as [$start, $end]) {
            $markup[] = [$this->toOriginal($start), $this->toOriginal($end)];
        }
        usort($markup, static fn (array $a, array $b): int => $a <=> $b);

        // Keep $next's own corrections, then map through $this.
        return new self($next->points, $next->diffs, $markup, $this->withoutMarkup());
    }

    public function isIdentity(): bool
    {
        return [] === $this->points && [] === $this->markup && null === $this->inner;
    }

    public function toOriginal(int $offset): int
    {
        $corrected = $offset + $this->diffAt($offset);

        return null === $this->inner ? $corrected : $this->inner->toOriginal($corrected);
    }

    /**
     * Removed markup ranges [start, end) in original coordinates, sorted.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function markup(): array
    {
        return $this->markup;
    }

    /**
     * Splits [start, end) of the original text into the ranges that are not
     * markup (empty ranges are dropped).
     *
     * @return list<array{0: int, 1: int}>
     */
    public function textSegments(int $start, int $end): array
    {
        $segments = [];
        $cursor = $start;

        foreach ($this->markup as [$markupStart, $markupEnd]) {
            if ($markupEnd <= $cursor || $markupStart >= $end) {
                continue;
            }
            if ($markupStart > $cursor) {
                $segments[] = [$cursor, $markupStart];
            }
            $cursor = max($cursor, $markupEnd);
        }

        if ($cursor < $end) {
            $segments[] = [$cursor, $end];
        }

        return $segments;
    }

    private function diffAt(int $offset): int
    {
        // Last point <= offset (binary search).
        $low = 0;
        $high = \count($this->points) - 1;
        $found = -1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->points[$mid] <= $offset) {
                $found = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return -1 === $found ? 0 : $this->diffs[$found];
    }

    private function withoutMarkup(): self
    {
        return new self($this->points, $this->diffs, [], $this->inner);
    }
}
