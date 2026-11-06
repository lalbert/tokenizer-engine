<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\WordList;

/**
 * Keeps only the terms of a list, like Elasticsearch's `keep` filter.
 */
final class KeepWordsTokenFilter extends AbstractTermTokenFilter
{
    /**
     * @var array<string, true>
     */
    private readonly array $words;

    /**
     * @param list<string> $keepWords
     */
    public function __construct(array $keepWords = [], ?string $keepWordsPath = null, private readonly bool $ignoreCase = false)
    {
        if (null !== $keepWordsPath) {
            $keepWords = array_merge($keepWords, WordList::words($keepWordsPath));
        }

        $set = [];
        foreach ($keepWords as $word) {
            $set[$this->normalize($word)] = true;
        }
        $this->words = $set;
    }

    protected function filterTerm(string $term, Token $token): ?string
    {
        return isset($this->words[$this->normalize($term)]) ? $term : null;
    }

    private function normalize(string $term): string
    {
        return $this->ignoreCase ? mb_strtolower($term, 'UTF-8') : $term;
    }
}
