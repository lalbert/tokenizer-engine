<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\WordList;

/**
 * Applies synonyms in Solr format, like Elasticsearch's `synonym` filter:
 *
 *  - "ipod, i-pod, i pod"          equivalent synonyms (all are emitted when
 *                                  $expand is true, otherwise only the first)
 *  - "universe, cosmos => cosmos"  explicit mapping (left side is replaced)
 *
 * Multi-word synonyms are supported (longest match first). Synonyms are
 * matched against terms as they are at this point of the chain, so put this
 * filter after the lowercase filter or use $ignoreCase.
 */
final class SynonymTokenFilter extends AbstractTokenFilter
{
    /**
     * @var array<string, list<list<string>>> normalized phrase key => replacement phrases
     */
    private array $rules = [];

    private int $maxPhraseLength = 1;

    /**
     * @param array<array-key, string|list<string>> $synonyms Solr rules ("a, b => c"), or an associative
     *                                                        array (['a' => ['b', 'c']]) for explicit mappings
     */
    public function __construct(
        array $synonyms = [],
        ?string $synonymsPath = null,
        private readonly bool $expand = true,
        private readonly bool $ignoreCase = false,
    ) {
        foreach ($synonyms as $key => $value) {
            if (\is_string($key)) {
                $this->addRule([$key], (array) $value);
            } else {
                foreach ((array) $value as $rule) {
                    $this->parseRule($rule);
                }
            }
        }

        if (null !== $synonymsPath) {
            foreach (WordList::lines($synonymsPath) as $line) {
                $this->parseRule($line);
            }
        }
    }

    protected function process(array $tokens): array
    {
        if ([] === $this->rules) {
            return $tokens;
        }

        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count;) {
            for ($length = min($this->maxPhraseLength, $count - $i); $length >= 1; --$length) {
                $slice = \array_slice($tokens, $i, $length);
                $key = $this->key(array_map(static fn (Token $t): string => $t->term, $slice));

                if (isset($this->rules[$key])) {
                    array_push($result, ...$this->replace($slice, $this->rules[$key]));
                    $i += $length;
                    continue 2;
                }
            }

            $result[] = $tokens[$i];
            ++$i;
        }

        return $result;
    }

    /**
     * @param list<Token>        $original
     * @param list<list<string>> $phrases
     *
     * @return list<Token>
     */
    private function replace(array $original, array $phrases): array
    {
        $originalKey = $this->key(array_map(static fn (Token $t): string => $t->term, $original));
        $first = $original[0];
        $last = $original[\count($original) - 1];
        $result = [];
        $keepOriginal = false;

        foreach ($phrases as $phrase) {
            if ($this->key($phrase) === $originalKey) {
                $keepOriginal = true;
                continue;
            }

            foreach ($phrase as $j => $word) {
                $result[] = new Token($word, $first->position + $j, $first->startOffset, $last->endOffset, Token::TYPE_SYNONYM);
            }
        }

        return $keepOriginal ? [...$original, ...$result] : $result;
    }

    private function parseRule(string $rule): void
    {
        $rule = trim($rule);
        if ('' === $rule) {
            return;
        }

        if (str_contains($rule, '=>')) {
            $sides = explode('=>', $rule);
            if (2 !== \count($sides)) {
                throw new \InvalidArgumentException(\sprintf('Invalid synonym rule "%s".', $rule));
            }
            $this->addRule(self::phrases($sides[0]), self::phrases($sides[1]));

            return;
        }

        $phrases = self::phrases($rule);
        foreach ($phrases as $phrase) {
            $this->addRule([$phrase], $this->expand ? $phrases : [$phrases[0]]);
        }
    }

    /**
     * @param list<string> $from phrases
     * @param list<string> $to   phrases
     */
    private function addRule(array $from, array $to): void
    {
        $replacements = [];
        foreach ($to as $phrase) {
            $words = self::words($phrase);
            if ([] !== $words) {
                $replacements[] = $words;
            }
        }

        if ([] === $replacements) {
            throw new \InvalidArgumentException('A synonym rule has an empty right-hand side.');
        }

        foreach ($from as $phrase) {
            $words = self::words($phrase);
            if ([] === $words) {
                continue;
            }

            $key = $this->key($words);
            $existing = $this->rules[$key] ?? [];
            foreach ($replacements as $replacement) {
                if (!\in_array($replacement, $existing, true)) {
                    $existing[] = $replacement;
                }
            }
            $this->rules[$key] = $existing;
            $this->maxPhraseLength = max($this->maxPhraseLength, \count($words));
        }
    }

    /**
     * @param list<string> $words
     */
    private function key(array $words): string
    {
        $key = implode("\0", $words);

        return $this->ignoreCase ? mb_strtolower($key, 'UTF-8') : $key;
    }

    /**
     * @return list<string>
     */
    private static function phrases(string $side): array
    {
        $phrases = preg_split('/(?<!\\\\),/u', $side) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $p): string => trim(str_replace('\,', ',', $p)), $phrases),
            static fn (string $p): bool => '' !== $p,
        ));
    }

    /**
     * @return list<string>
     */
    private static function words(string $phrase): array
    {
        return preg_split('/\s+/u', trim($phrase), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
