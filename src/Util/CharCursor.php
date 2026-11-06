<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

/**
 * Extracts substrings by character (code point) offsets in a long UTF-8
 * text in linear time (mb_substr() scans from the beginning of the string at
 * every call): give all the offsets to prepare() first, or request them in
 * increasing order.
 *
 * @internal
 */
final class CharCursor
{
    private int $char = 0;
    private int $byte = 0;

    /**
     * @var array<int, int> character offset => byte offset
     */
    private array $bytes = [];

    public function __construct(private readonly string $text)
    {
    }

    /**
     * Computes the byte offsets of these character offsets in one pass.
     *
     * @param list<int> $offsets
     */
    public function prepare(array $offsets): self
    {
        $offsets = array_unique($offsets);
        sort($offsets);
        foreach ($offsets as $offset) {
            $this->bytes[$offset] = $this->byteAt($offset);
        }

        return $this;
    }

    public function substring(int $start, int $end): string
    {
        $from = $this->byteAt($start);

        return substr($this->text, $from, $this->byteAt($end) - $from);
    }

    private function byteAt(int $char): int
    {
        if (isset($this->bytes[$char])) {
            return $this->bytes[$char];
        }

        if ($char < $this->char) {
            // Going backwards: restart from the beginning.
            $this->char = 0;
            $this->byte = 0;
        }

        $delta = $char - $this->char;
        if ($delta > 0) {
            // A code point takes at most 4 bytes.
            $this->byte += \strlen(mb_substr(substr($this->text, $this->byte, $delta * 4), 0, $delta, 'UTF-8'));
            $this->char = $char;
        }

        return $this->byte;
    }
}
