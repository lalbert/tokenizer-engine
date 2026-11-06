<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Splits terms on non alphanumeric characters, case changes and letter/digit
 * transitions ("PowerShot-500" gives Power, Shot, 500), like Elasticsearch's
 * `word_delimiter` filter. Unicode aware ("ÉcoleNormale" gives École, Normale).
 *
 * Sub-tokens keep the position of the original token; their offsets are
 * narrowed to the part when the original offsets are exact.
 */
final class WordDelimiterTokenFilter extends AbstractTokenFilter
{
    public const CHAR_TYPE_BREAK = 'BREAK';
    public const CHAR_TYPE_UPPER = 'UPPER';
    public const CHAR_TYPE_LOWER = 'LOWER';
    public const CHAR_TYPE_DIGIT = 'DIGIT';

    public const WORD_TYPE_WORD = 'WORD';
    public const WORD_TYPE_NUMBER = 'NUMBER';

    private const DEFAULTS = [
        'generateWordParts' => true,
        'generateNumberParts' => true,
        'catenateWords' => false,
        'catenateNumbers' => false,
        'catenateAll' => false,
        'splitOnCaseChange' => true,
        'preserveOriginal' => false,
        'splitOnNumerics' => true,
        'stemEnglishPossessive' => true,
        'protectedWords' => [],
    ];

    private bool $generateWordParts = true;
    private bool $generateNumberParts = true;
    private bool $catenateWords = false;
    private bool $catenateNumbers = false;
    private bool $catenateAll = false;
    private bool $splitOnCaseChange = true;
    private bool $preserveOriginal = false;
    private bool $splitOnNumerics = true;
    private bool $stemEnglishPossessive = true;

    /**
     * @var array<string, true>
     */
    private array $protectedWords = [];

    /**
     * @param array<string, bool|list<string>> $config see DEFAULTS for the keys
     */
    public function __construct(array $config = [])
    {
        foreach (array_merge(self::DEFAULTS, $config) as $key => $value) {
            if (!\array_key_exists($key, self::DEFAULTS)) {
                throw new \InvalidArgumentException(\sprintf('%s is not a valid parameter.', $key));
            }

            if ('protectedWords' === $key) {
                $this->protectedWords = array_fill_keys(array_map('strval', (array) $value), true);
                continue;
            }

            $this->$key = (bool) $value;
        }
    }

    protected function process(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if (isset($this->protectedWords[$token->term])) {
                $result[] = $token;
                continue;
            }

            $emitted = $this->delimit($token);

            if ($this->preserveOriginal && !(1 === \count($emitted) && $emitted[0]->term === $token->term)) {
                array_unshift($emitted, $token);
            }

            array_push($result, ...$emitted);
        }

        return $result;
    }

    /**
     * @return list<Token>
     */
    private function delimit(Token $token): array
    {
        $parts = $this->split($token->term);
        $exact = $token->hasExactOffsets();
        $make = static fn (string $term, int $start, int $end): Token => $exact
            ? $token->with(term: $term, startOffset: (int) $token->startOffset + $start, endOffset: (int) $token->startOffset + $end)
            : $token->withTerm($term);

        $result = [];
        $count = \count($parts);

        if ($this->catenateAll && $count > 1) {
            $result[] = $make(implode('', array_column($parts, 0)), $parts[0][1], $parts[$count - 1][2]);
        }

        $run = [];
        foreach ($parts as $i => $part) {
            [$text, $start, $end, $type] = $part;

            if (self::WORD_TYPE_WORD === $type ? $this->generateWordParts : $this->generateNumberParts) {
                $result[] = $make($text, $start, $end);
            }

            $run[] = $part;
            $next = $parts[$i + 1] ?? null;
            if (null !== $next && $next[3] === $type) {
                continue;
            }

            $catenate = self::WORD_TYPE_WORD === $type ? $this->catenateWords : $this->catenateNumbers;
            if ($catenate && \count($run) > 1) {
                $result[] = $make(implode('', array_column($run, 0)), $run[0][1], $run[\count($run) - 1][2]);
            }
            $run = [];
        }

        return $result;
    }

    /**
     * @return list<array{0: string, 1: int, 2: int, 3: string}> text, start, end, word type
     */
    private function split(string $term): array
    {
        $chars = mb_str_split($term, 1, 'UTF-8');
        $count = \count($chars);
        $types = array_map($this->charType(...), $chars);

        // Combining marks belong to the previous character.
        for ($i = 0; $i < $count; ++$i) {
            if ('MARK' === $types[$i]) {
                $types[$i] = $types[$i - 1] ?? self::CHAR_TYPE_BREAK;
            }
        }

        if ($this->stemEnglishPossessive) {
            for ($i = 1; $i < $count - 1; ++$i) {
                if (\in_array($chars[$i], ["'", "\u{2019}"], true)
                    && \in_array($chars[$i + 1], ['s', 'S'], true)
                    && self::CHAR_TYPE_BREAK !== $types[$i - 1]
                    && self::CHAR_TYPE_BREAK === ($types[$i + 2] ?? self::CHAR_TYPE_BREAK)
                ) {
                    $types[$i] = $types[$i + 1] = self::CHAR_TYPE_BREAK;
                }
            }
        }

        $parts = [];
        $start = null;
        for ($i = 0; $i <= $count; ++$i) {
            $type = $types[$i] ?? self::CHAR_TYPE_BREAK;
            $previous = $i > 0 ? $types[$i - 1] : self::CHAR_TYPE_BREAK;

            $boundary = self::CHAR_TYPE_BREAK === $type
                || ($this->splitOnCaseChange && self::CHAR_TYPE_LOWER === $previous && self::CHAR_TYPE_UPPER === $type)
                || ($this->splitOnNumerics && self::CHAR_TYPE_BREAK !== $previous && (self::CHAR_TYPE_DIGIT === $previous) !== (self::CHAR_TYPE_DIGIT === $type));

            if ($boundary && null !== $start) {
                $text = implode('', \array_slice($chars, $start, $i - $start));
                $parts[] = [$text, $start, $i, preg_match('/^\p{N}+$/u', $text) ? self::WORD_TYPE_NUMBER : self::WORD_TYPE_WORD];
                $start = null;
            }

            if (self::CHAR_TYPE_BREAK !== $type && null === $start) {
                $start = $i;
            }
        }

        return $parts;
    }

    private function charType(string $char): string
    {
        return match (true) {
            1 === preg_match('/^[\p{Lu}\p{Lt}]$/u', $char) => self::CHAR_TYPE_UPPER,
            1 === preg_match('/^\p{L}$/u', $char) => self::CHAR_TYPE_LOWER,
            1 === preg_match('/^\p{N}$/u', $char) => self::CHAR_TYPE_DIGIT,
            1 === preg_match('/^\p{M}$/u', $char) => 'MARK',
            default => self::CHAR_TYPE_BREAK,
        };
    }
}
