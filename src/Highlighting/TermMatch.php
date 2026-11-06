<?php

declare(strict_types=1);

namespace TokenizerEngine\Highlighting;

/**
 * A passage of a text matching query terms. Offsets are character offsets
 * (code points) in the original text, markup included, like Elasticsearch.
 */
final readonly class TermMatch
{
    /**
     * @param list<string>                                    $terms    analyzed query terms found in the passage
     * @param list<array{start: int, end: int, text: string}> $segments parts of the passage that are text, not markup
     *                                                                  (HTML tags removed by a character filter)
     */
    public function __construct(
        public array $terms,
        public int $start,
        public int $end,
        public string $text,
        public array $segments,
    ) {
    }
}
