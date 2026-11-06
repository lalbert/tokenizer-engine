<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

/**
 * Grammar based tokenizer following the Unicode Text Segmentation algorithm
 * (UAX #29), like Elasticsearch's `standard` tokenizer.
 *
 * Uses ICU (ext-intl) when available, otherwise a regular expression that
 * approximates UAX #29 for alphabetic scripts.
 */
final class StandardTokenizer extends AbstractTokenizer
{
    private const FALLBACK_PATTERN = '/[\p{L}\p{M}\p{N}\p{Pc}]+(?:[\'’.:·][\p{L}\p{M}\p{N}\p{Pc}]+|,\p{N}+)*/u';

    private readonly int $maxTokenLength;

    public function __construct(int $maxTokenLength = 255, private readonly bool $useIntl = true)
    {
        if ($maxTokenLength < 1) {
            throw new \InvalidArgumentException('maxTokenLength must be greater than 0.');
        }

        $this->maxTokenLength = $maxTokenLength;
    }

    protected function split(string $text): iterable
    {
        foreach ($this->segment($text) as [$term, $offset]) {
            if (mb_strlen($term) <= $this->maxTokenLength) {
                yield [$term, $offset];
                continue;
            }

            // Like Lucene, a word longer than maxTokenLength is cut, and each
            // piece is segmented again ("dell'" gives "dell").
            foreach (mb_str_split($term, max(1, $this->maxTokenLength), 'UTF-8') as $i => $chunk) {
                foreach ($this->segment($chunk) as [$piece, $pieceOffset]) {
                    yield [$piece, $offset + $i * $this->maxTokenLength + $pieceOffset];
                }
            }
        }
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function segment(string $text): array
    {
        return $this->useIntl && class_exists(\IntlBreakIterator::class)
            ? $this->splitWithIntl($text)
            : self::matches(self::FALLBACK_PATTERN, $text);
    }

    protected function tokenType(string $term): string
    {
        return preg_match('/^\p{N}+$/u', $term) ? '<NUM>' : '<ALPHANUM>';
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function splitWithIntl(string $text): array
    {
        if ('' === $text) {
            return [];
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        $iterator = \IntlBreakIterator::createWordInstance('root');
        if (!$iterator instanceof \IntlRuleBasedBreakIterator) {
            return self::matches(self::FALLBACK_PATTERN, $text);
        }
        $iterator->setText($text);

        $byteMatches = [];
        $start = $iterator->first();
        for ($end = $iterator->next(); \IntlBreakIterator::DONE !== $end; $start = $end, $end = $iterator->next()) {
            if ($iterator->getRuleStatus() >= \IntlBreakIterator::WORD_NONE_LIMIT) {
                $byteMatches[] = [substr($text, $start, $end - $start), $start];
            }
        }

        return self::toCharOffsets($text, $byteMatches);
    }
}
