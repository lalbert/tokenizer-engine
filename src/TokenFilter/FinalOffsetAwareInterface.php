<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

/**
 * Filters whose output depends on where the text ends (not only on its
 * tokens). TokenizerEngine calls withFinalOffset() with the length of the
 * character-filtered text before running them.
 */
interface FinalOffsetAwareInterface
{
    public function withFinalOffset(int $finalOffset): static;
}
