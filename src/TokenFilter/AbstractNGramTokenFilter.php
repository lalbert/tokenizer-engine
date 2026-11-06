<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

abstract class AbstractNGramTokenFilter extends AbstractTermTokenFilter
{
    public function __construct(
        protected readonly int $minGram = 1,
        protected readonly int $maxGram = 2,
        private readonly bool $preserveOriginal = true,
    ) {
        if ($minGram < 1 || $maxGram < 1) {
            throw new \InvalidArgumentException('minGram and maxGram must be greater than 0.');
        }

        if ($minGram > $maxGram) {
            throw new \InvalidArgumentException('maxGram must be greater than or equal to minGram.');
        }
    }

    protected function filterTerm(string $term, Token $token): array
    {
        $nGrams = $this->nGrams(mb_str_split($term, 1, 'UTF-8'));

        if ($this->preserveOriginal && !\in_array($term, $nGrams, true)) {
            array_unshift($nGrams, $term);
        }

        return $nGrams;
    }

    /**
     * @param list<string> $chars
     *
     * @return list<string>
     */
    abstract protected function nGrams(array $chars): array;
}
