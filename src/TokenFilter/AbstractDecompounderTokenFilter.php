<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\WordList;

/**
 * Base class of the compound word filters (port of Lucene's
 * CompoundWordTokenFilterBase): each token of at least $minWordSize
 * characters is kept and followed by the subwords found in it, at the same
 * position and with the same offsets, typed "subword".
 */
abstract class AbstractDecompounderTokenFilter extends AbstractTokenFilter
{
    public const TYPE = 'subword';

    /**
     * @var array<string, true>
     */
    protected readonly array $dictionary;

    /**
     * @param list<string> $wordList
     */
    public function __construct(
        array $wordList = [],
        ?string $wordListPath = null,
        protected readonly int $minWordSize = 5,
        protected readonly int $minSubwordSize = 2,
        protected readonly int $maxSubwordSize = 15,
        protected readonly bool $onlyLongestMatch = false,
    ) {
        if (null !== $wordListPath) {
            $wordList = array_merge($wordList, WordList::words($wordListPath));
        }

        if ($minWordSize < 0 || $minSubwordSize < 0 || $maxSubwordSize < 0) {
            throw new \InvalidArgumentException('Word sizes cannot be negative.');
        }

        $this->dictionary = array_fill_keys(array_map('strval', $wordList), true);
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $result[] = $token;

            $chars = mb_str_split($token->term, 1, 'UTF-8');
            if (\count($chars) < $this->minWordSize) {
                continue;
            }

            foreach ($this->decompose($chars) as [$start, $length]) {
                $result[] = $token->with(term: implode('', \array_slice($chars, $start, $length)), type: self::TYPE);
            }
        }

        return $result;
    }

    /**
     * @param list<string> $chars
     */
    protected function inDictionary(array $chars, int $start, int $length): bool
    {
        return isset($this->dictionary[implode('', \array_slice($chars, $start, $length))]);
    }

    /**
     * @param list<string> $chars the characters of the token
     *
     * @return list<array{0: int, 1: int}> subwords as [start, length]
     */
    abstract protected function decompose(array $chars): array;
}
