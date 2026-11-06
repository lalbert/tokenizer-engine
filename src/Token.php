<?php

declare(strict_types=1);

namespace TokenizerEngine;

/**
 * A single term produced by the analysis chain, with its position in the
 * stream and its character offsets in the (character-filtered) input text.
 *
 * Offsets are expressed in Unicode code points (not bytes) and are null when
 * the token was built from a bare string rather than produced by a tokenizer.
 */
final readonly class Token
{
    public const TYPE_WORD = 'word';
    public const TYPE_SYNONYM = 'SYNONYM';

    public function __construct(
        public string $term,
        public int $position = 0,
        public ?int $startOffset = null,
        public ?int $endOffset = null,
        public string $type = self::TYPE_WORD,
    ) {
    }

    public function withTerm(string $term): self
    {
        return new self($term, $this->position, $this->startOffset, $this->endOffset, $this->type);
    }

    public function with(
        ?string $term = null,
        ?int $position = null,
        ?int $startOffset = null,
        ?int $endOffset = null,
        ?string $type = null,
    ): self {
        return new self(
            $term ?? $this->term,
            $position ?? $this->position,
            $startOffset ?? $this->startOffset,
            $endOffset ?? $this->endOffset,
            $type ?? $this->type,
        );
    }

    /**
     * Offsets are only reliable for sub-token computations when the term still
     * spans exactly the original text (same length).
     */
    public function hasExactOffsets(): bool
    {
        return null !== $this->startOffset
            && null !== $this->endOffset
            && $this->endOffset - $this->startOffset === mb_strlen($this->term);
    }

    /**
     * Same shape as one entry of Elasticsearch's `_analyze` API response.
     *
     * @return array{token: string, start_offset: int|null, end_offset: int|null, type: string, position: int}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->term,
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'type' => $this->type,
            'position' => $this->position,
        ];
    }

    public function __toString(): string
    {
        return $this->term;
    }
}
