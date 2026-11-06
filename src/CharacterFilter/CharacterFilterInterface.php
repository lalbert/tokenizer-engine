<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

interface CharacterFilterInterface
{
    public function filter(string $text): string;
}
