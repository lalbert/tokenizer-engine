<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\OffsetMap;

/**
 * Character filters that can map offsets of their output back to their
 * input, so that token offsets refer to the original text (like
 * Elasticsearch). Filters implementing only CharacterFilterInterface are
 * assumed not to move characters.
 */
interface OffsetMappingCharacterFilterInterface extends CharacterFilterInterface
{
    /**
     * @return array{0: string, 1: OffsetMap}
     */
    public function filterWithOffsets(string $text): array;
}
