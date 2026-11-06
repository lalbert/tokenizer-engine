<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Emits word n-grams ("please divide this" gives please, please divide,
 * divide, divide this, this), like Elasticsearch's `shingle` filter.
 *
 * Position gaps (stop words removed earlier) are filled with $fillerToken,
 * at most maxShingleSize - 1 fillers per gap, and positions are renumbered
 * like Elasticsearch does. When several tokens share a position (synonyms),
 * only the first one takes part in shingles; the others are kept as unigrams.
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
        // Slots: real tokens and fillers; a filler is null.
        /** @var list<Token|null> $slots */
        $slots = [];
        /** @var array<int, list<Token>> $extras tokens sharing the position of slot i */
        $extras = [];
        $previous = -1;

        foreach ($tokens as $token) {
            if ($token->position === $previous && [] !== $slots) {
                $extras[\count($slots) - 1][] = $token;
                continue;
            }

            $gap = min(max(0, $token->position - $previous - 1), $this->maxShingleSize - 1);
            for ($i = 0; $i < $gap; ++$i) {
                $slots[] = null;
            }
            $slots[] = $token;
            $previous = $token->position;
        }

        $count = \count($slots);
        // A filler takes the offsets of the start of the next real token.
        $offsets = [];
        $next = null;
        for ($i = $count - 1; $i >= 0; --$i) {
            $slot = $slots[$i];
            if (null !== $slot) {
                $next = $slot->startOffset;
                $offsets[$i] = [$slot->startOffset, $slot->endOffset];
            } else {
                $offsets[$i] = [$next, $next];
            }
        }

        $unigrams = [];
        $shingles = [];
        foreach ($slots as $i => $slot) {
            $unigrams[$i] = null === $slot ? [] : array_map(
                static fn (Token $t): Token => $t->with(position: $i),
                [$slot, ...($extras[$i] ?? [])],
            );

            for ($size = $this->minShingleSize; $size <= $this->maxShingleSize && $i + $size <= $count; ++$size) {
                $window = \array_slice($slots, $i, $size);
                if ([] === array_filter($window)) {
                    continue;
                }

                $terms = array_map(fn (?Token $s): string => null === $s ? $this->fillerToken : $s->term, $window);
                $shingles[$i][] = new Token(
                    implode($this->tokenSeparator, $terms),
                    $i,
                    $offsets[$i][0],
                    $offsets[$i + $size - 1][1],
                    self::TYPE,
                );
            }
        }

        $withUnigrams = $this->outputUnigrams || ([] === $shingles && $this->outputUnigramsIfNoShingles);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            if ($withUnigrams) {
                array_push($result, ...$unigrams[$i]);
            }
            array_push($result, ...($shingles[$i] ?? []));
        }

        return $result;
    }
}
