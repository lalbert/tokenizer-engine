<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Replaces the whole stream with a MinHash signature of $hashCount tokens
 * ("0_3f2a...", "1_9b1c..."), to estimate the Jaccard similarity of two
 * texts: see similarity(). Put a ShingleTokenFilter before it to compare
 * documents on word sequences rather than single words.
 *
 * Same purpose as Elasticsearch's `min_hash` filter, but a different
 * algorithm (k seeded xxh64 hash functions, no buckets): signatures are not
 * compatible with Elasticsearch's.
 */
final class MinHashTokenFilter extends AbstractTokenFilter
{
    public const TYPE = 'MIN_HASH';

    public function __construct(private readonly int $hashCount = 128)
    {
        if ($hashCount < 1) {
            throw new \InvalidArgumentException('hashCount must be greater than 0.');
        }
    }

    /**
     * Estimated Jaccard similarity (0 to 1) of two signatures produced with
     * the same hash count.
     *
     * @param list<string|Token> $a
     * @param list<string|Token> $b
     */
    public static function similarity(array $a, array $b): float
    {
        $a = array_map('strval', $a);
        $b = array_map('strval', $b);

        if ([] === $a || \count($a) !== \count($b)) {
            throw new \InvalidArgumentException('Signatures must be non empty and of the same size.');
        }

        return \count(array_intersect_assoc($a, $b)) / \count($a);
    }

    protected function process(array $tokens): array
    {
        $terms = array_unique(array_map(static fn (Token $t): string => $t->term, $tokens));
        if ([] === $terms) {
            return [];
        }

        $start = $tokens[0]->startOffset;
        $end = $tokens[\count($tokens) - 1]->endOffset;
        $signature = [];

        for ($i = 0; $i < $this->hashCount; ++$i) {
            $min = null;
            foreach ($terms as $term) {
                $hash = hash('xxh64', $term, false, ['seed' => $i]);
                if (null === $min || $hash < $min) {
                    $min = $hash;
                }
            }
            $signature[] = new Token($i.'_'.$min, 0, $start, $end, self::TYPE);
        }

        return $signature;
    }
}
