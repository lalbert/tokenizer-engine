<?php

declare(strict_types=1);

namespace TokenizerEngine\Highlighting;

use TokenizerEngine\Token;
use TokenizerEngine\TokenizerEngine;

/**
 * Finds where the terms of a query appear in a text: both are analyzed (with
 * the same analyzer, or with a distinct query analyzer like Elasticsearch's
 * `search_analyzer`), and the tokens of the text whose term is in the query
 * give the matching passages, with their offsets in the original text.
 *
 * Rendering (<mark>, snippets, escaping) is left to the caller: each
 * TermMatch has its offsets and the text segments to wrap, which skip the
 * markup removed by character filters, so that wrapping them keeps HTML
 * valid. Phrases are not matched as a whole: each term matches on its own.
 */
final class TermMatcher
{
    private readonly TokenizerEngine $queryAnalyzer;

    public function __construct(private readonly TokenizerEngine $textAnalyzer, ?TokenizerEngine $queryAnalyzer = null)
    {
        $this->queryAnalyzer = $queryAnalyzer ?? $textAnalyzer;
    }

    /**
     * @return list<TermMatch> sorted by position in the text, overlapping passages merged
     */
    public function match(string $text, string $query): array
    {
        $queryTerms = array_fill_keys($this->queryAnalyzer->tokenize($query), true);
        if ([] === $queryTerms) {
            return [];
        }

        [$tokens, $map] = $this->textAnalyzer->tokensAndOffsetMap($text);

        $hits = array_values(array_filter(
            $tokens,
            static fn (Token $t): bool => isset($queryTerms[$t->term]) && null !== $t->startOffset && null !== $t->endOffset,
        ));
        usort($hits, static fn (Token $a, Token $b): int => [$a->startOffset, $a->endOffset] <=> [$b->startOffset, $b->endOffset]);

        // Merge overlapping passages (n-grams, synonyms... at the same place).
        /** @var list<array{start: int, end: int, terms: list<string>}> $passages */
        $passages = [];
        foreach ($hits as $hit) {
            $start = (int) $hit->startOffset;
            $end = (int) $hit->endOffset;
            $last = array_key_last($passages);

            if (null !== $last && $start < $passages[$last]['end']) {
                $passages[$last]['end'] = max($passages[$last]['end'], $end);
                if (!\in_array($hit->term, $passages[$last]['terms'], true)) {
                    $passages[$last]['terms'][] = $hit->term;
                }
                continue;
            }

            $passages[] = ['start' => $start, 'end' => $end, 'terms' => [$hit->term]];
        }

        $slice = static fn (int $start, int $end): string => mb_substr($text, $start, $end - $start, 'UTF-8');

        return array_map(
            static fn (array $p): TermMatch => new TermMatch(
                $p['terms'],
                $p['start'],
                $p['end'],
                $slice($p['start'], $p['end']),
                array_map(
                    static fn (array $s): array => ['start' => $s[0], 'end' => $s[1], 'text' => $slice($s[0], $s[1])],
                    $map->textSegments($p['start'], $p['end']),
                ),
            ),
            $passages,
        );
    }
}
