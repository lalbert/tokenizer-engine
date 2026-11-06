<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Emits word n-grams ("please divide this" gives please, please divide,
 * divide, divide this, this), like Elasticsearch's `shingle` filter.
 *
 * Position gaps (stop words removed earlier) are filled with $fillerToken.
 * When several tokens share a position (synonyms), only the first one takes
 * part in shingles; the others are kept as unigrams.
 */
final class ShingleTokenFilter extends AbstractTokenFilter
{
    public const TYPE = 'shingle';

    public function __construct(
        private readonly int $minShingleSize = 2,
        private readonly int $maxShingleSize = 2,
        private readonly bool $outputUnigrams = true,
        private readonly bool $outputUnigramsIfNoShingles = false,
        private readonly string $tokenSeparator = ' ',
        private readonly string $fillerToken = '_',
    ) {
        if ($minShingleSize < 2 || $maxShingleSize < $minShingleSize) {
            throw new \InvalidArgumentException('Expected 2 <= minShingleSize <= maxShingleSize.');
        }
    }

    protected function process(array $tokens): array
    {
        // One slot per position, gaps filled with null (filler).
        $slots = [];
        $extras = [];
        $previous = null;
        foreach ($tokens as $token) {
            if (null !== $previous && $token->position === $previous) {
                $extras[$token->position][] = $token;
                continue;
            }
            if (null !== $previous) {
                for ($gap = $previous + 1; $gap < $token->position; ++$gap) {
                    $slots[] = [null, $gap];
                }
            }
            $slots[] = [$token, $token->position];
            $previous = $token->position;
        }

        $unigrams = [];
        $shingles = [];
        $count = \count($slots);

        foreach ($slots as $i => [$token, $position]) {
            $unigrams[$i] = null === $token ? [] : [$token, ...($extras[$position] ?? [])];

            for ($size = $this->minShingleSize; $size <= $this->maxShingleSize && $i + $size <= $count; ++$size) {
                $window = \array_slice($slots, $i, $size);
                $real = array_values(array_filter(array_column($window, 0)));
                if ([] === $real) {
                    continue;
                }

                $terms = array_map(fn (array $slot): string => null === $slot[0] ? $this->fillerToken : $slot[0]->term, $window);
                $shingles[$i][] = new Token(
                    implode($this->tokenSeparator, $terms),
                    $position,
                    $real[0]->startOffset,
                    $real[\count($real) - 1]->endOffset,
                    self::TYPE,
                );
            }
        }

        $withUnigrams = $this->outputUnigrams || ([] === $shingles && $this->outputUnigramsIfNoShingles);
        $result = [];
        foreach (array_keys($slots) as $i) {
            if ($withUnigrams) {
                array_push($result, ...$unigrams[$i]);
            }
            array_push($result, ...($shingles[$i] ?? []));
        }

        return $result;
    }
}
