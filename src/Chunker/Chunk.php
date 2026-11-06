<?php

declare(strict_types=1);

namespace TokenizerEngine\Chunker;

/**
 * A passage of a text, with its offsets (in characters) in the original text.
 */
final readonly class Chunk
{
    public function __construct(
        public string $text,
        public int $startOffset,
        public int $endOffset,
        public int $index,
        public int $size,
    ) {
    }

    /**
     * @return array{index: int, text: string, start_offset: int, end_offset: int, size: int}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'text' => $this->text,
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'size' => $this->size,
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
