<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Util\WordList;

/**
 * Replaces terms with a custom stem and flags them as keywords, so that the
 * stemmers placed after it leave them alone, like Elasticsearch's
 * `stemmer_override` filter. Rules: "running, runs => run".
 */
final class StemmerOverrideTokenFilter extends AbstractTokenFilter
{
    /**
     * @var array<string, string>
     */
    private array $overrides = [];

    /**
     * @param array<array-key, string> $rules "a, b => c" rules or ['a' => 'c'] pairs
     */
    public function __construct(array $rules = [], ?string $rulesPath = null)
    {
        if (null !== $rulesPath) {
            $rules = array_merge($rules, WordList::lines($rulesPath));
        }

        foreach ($rules as $key => $rule) {
            if (\is_string($key)) {
                $this->overrides[$key] = $rule;
                continue;
            }

            $sides = explode('=>', $rule);
            if (2 !== \count($sides) || '' === ($stem = trim($sides[1]))) {
                throw new \InvalidArgumentException(\sprintf('Invalid stemmer override rule "%s", expected "word => stem".', $rule));
            }

            foreach (explode(',', $sides[0]) as $word) {
                if ('' !== ($word = trim($word))) {
                    $this->overrides[$word] = $stem;
                }
            }
        }
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $stem = $token->keyword ? null : ($this->overrides[$token->term] ?? null);
            $result[] = null === $stem ? $token : $token->with(term: $stem, keyword: true);
        }

        return $result;
    }
}
