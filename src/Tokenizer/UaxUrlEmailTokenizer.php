<?php

declare(strict_types=1);

namespace TokenizerEngine\Tokenizer;

use TokenizerEngine\Token;

/**
 * Like StandardTokenizer, but keeps URLs and e-mail addresses as single
 * tokens (types <URL> and <EMAIL>), like Elasticsearch's `uax_url_email`
 * tokenizer.
 *
 * URLs are recognized when they start with a scheme (http://, https://,
 * ftp://) or with "www."; bare domain names ("example.com") are not.
 */
final class UaxUrlEmailTokenizer extends AbstractTokenizer
{
    private const PATTERN = '~(?<url>(?:(?:https?|ftp)://|www\.)[^\s<>"\'«»]+)|(?<email>(?:mailto:)?[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)*\.\p{L}{2,})~u';

    private readonly StandardTokenizer $standard;

    public function __construct(private readonly int $maxTokenLength = 255, bool $useIntl = true)
    {
        $this->standard = new StandardTokenizer($maxTokenLength, $useIntl);
    }

    public function tokenizeTokens(array $tokens): array
    {
        $result = [];
        $position = 0;

        foreach ($tokens as $token) {
            $base = $token->hasExactOffsets() ? (int) $token->startOffset : null;

            foreach ($this->segments($token->term) as [$term, $offset, $type]) {
                if (null === $type) {
                    $gap = new Token($term, 0, ($base ?? 0) + $offset, ($base ?? 0) + $offset + mb_strlen($term));
                    foreach ($this->standard->tokenizeTokens([$gap]) as $word) {
                        $result[] = $word->with(
                            position: $position++,
                            startOffset: null === $base ? $token->startOffset : $word->startOffset,
                            endOffset: null === $base ? $token->endOffset : $word->endOffset,
                        );
                    }
                    continue;
                }

                $result[] = new Token(
                    $term,
                    $position++,
                    null === $base ? $token->startOffset : $base + $offset,
                    null === $base ? $token->endOffset : $base + $offset + mb_strlen($term),
                    $type,
                );
            }
        }

        return $result;
    }

    protected function split(string $text): iterable
    {
        foreach ($this->tokenizeTokens([new Token($text, 0, 0, mb_strlen($text))]) as $token) {
            yield [$token->term, (int) $token->startOffset];
        }
    }

    /**
     * Splits the text into URL/e-mail matches and the text between them.
     *
     * @return list<array{0: string, 1: int, 2: string|null}> text, char offset, type (null = plain text)
     */
    private function segments(string $text): array
    {
        if (false === preg_match_all(self::PATTERN, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE | \PREG_UNMATCHED_AS_NULL)) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        $segments = [];
        $byte = 0;

        foreach ($matches as $match) {
            $value = (string) $match[0][0];
            $start = $match[0][1];
            $type = null !== $match['url'][0] ? '<URL>' : '<EMAIL>';

            if ('<URL>' === $type) {
                // Trailing punctuation belongs to the sentence, not to the URL.
                $value = (string) preg_replace('/[.,;:!?)\]}\'"]+$/u', '', (string) $value);
            }

            if ($start > $byte) {
                $segments[] = [substr($text, $byte, $start - $byte), $byte, null];
            }
            if (mb_strlen($value) > $this->maxTokenLength) {
                $segments[] = [$value, $start, null];
            } else {
                $segments[] = [$value, $start, $type];
            }
            $byte = $start + \strlen($value);
        }

        if ($byte < \strlen($text)) {
            $segments[] = [substr($text, $byte), $byte, null];
        }

        // Byte offsets to character offsets.
        return array_map(
            static fn (array $s): array => [$s[0], mb_strlen(substr($text, 0, $s[1])), $s[2]],
            $segments,
        );
    }
}
