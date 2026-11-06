<?php

declare(strict_types=1);

namespace TokenizerEngine;

/**
 * Normalizes a whole value into a single term (no word splitting), like an
 * Elasticsearch normalizer used on `keyword` fields: "  Café  " can give
 * "cafe" with the trim, lowercase and asciifolding filters.
 */
final class Normalizer
{
    public function __construct(private readonly TokenizerEngine $engine)
    {
    }

    public function normalize(string $text): string
    {
        return implode('', $this->engine->tokenize($text));
    }

    public function engine(): TokenizerEngine
    {
        return $this->engine;
    }
}
