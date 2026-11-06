<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Util\Text;
use TokenizerEngine\Util\WordList;

/**
 * Flags tokens as keywords so that stemmers leave them untouched ("Paris",
 * "iPhone"), like Elasticsearch's `keyword_marker` filter.
 */
final class KeywordMarkerTokenFilter extends AbstractTokenFilter
{
    /**
     * @var array<string, true>
     */
    private readonly array $keywords;

    private readonly ?string $regex;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        array $keywords = [],
        ?string $keywordsPath = null,
        ?string $keywordsPattern = null,
        private readonly bool $ignoreCase = false,
    ) {
        if (null !== $keywordsPath) {
            $keywords = array_merge($keywords, WordList::words($keywordsPath));
        }

        $set = [];
        foreach ($keywords as $keyword) {
            $set[$this->normalize($keyword)] = true;
        }
        $this->keywords = $set;
        $this->regex = null === $keywordsPattern ? null : Text::pattern('^(?:'.$keywordsPattern.')$', $ignoreCase ? 'i' : '');
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $isKeyword = isset($this->keywords[$this->normalize($token->term)])
                || (null !== $this->regex && 1 === preg_match($this->regex, $token->term));

            $result[] = $isKeyword && !$token->keyword ? $token->with(keyword: true) : $token;
        }

        return $result;
    }

    private function normalize(string $term): string
    {
        return $this->ignoreCase ? mb_strtolower($term, 'UTF-8') : $term;
    }
}
