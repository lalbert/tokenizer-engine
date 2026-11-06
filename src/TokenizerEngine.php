<?php

declare(strict_types=1);

namespace TokenizerEngine;

use TokenizerEngine\CharacterFilter\CharacterFilterInterface;
use TokenizerEngine\Elasticsearch\AnalysisConfig;
use TokenizerEngine\TokenFilter\TokenFilterInterface;
use TokenizerEngine\Tokenizer\AbstractTokenizer;
use TokenizerEngine\Tokenizer\TokenizerInterface;
use TokenizerEngine\Util\Tokens;

/**
 * An analysis chain: character filters, then tokenizers, then token filters,
 * applied in the order they were added (like an Elasticsearch analyzer).
 */
class TokenizerEngine
{
    /**
     * @var list<CharacterFilterInterface>
     */
    protected array $characterFilters = [];

    /**
     * @var list<TokenizerInterface>
     */
    protected array $tokenizers = [];

    /**
     * @var list<TokenFilterInterface>
     */
    protected array $tokenFilters = [];

    /**
     * @param array{
     *     characterFilters?: CharacterFilterInterface|iterable<CharacterFilterInterface>,
     *     tokenizers?: TokenizerInterface|iterable<TokenizerInterface>,
     *     tokenFilters?: TokenFilterInterface|iterable<TokenFilterInterface>,
     * } $config
     */
    public function __construct(array $config = [])
    {
        foreach (self::iterate($config['characterFilters'] ?? []) as $filter) {
            $this->addCharacterFilter($filter);
        }

        foreach (self::iterate($config['tokenizers'] ?? []) as $tokenizer) {
            $this->addTokenizer($tokenizer);
        }

        foreach (self::iterate($config['tokenFilters'] ?? []) as $filter) {
            $this->addTokenFilter($filter);
        }
    }

    /**
     * Builds an analyzer from Elasticsearch index settings (array or JSON).
     *
     * @param array<string, mixed>|string $settings
     */
    public static function fromElasticsearch(array|string $settings, string $analyzer = 'default'): self
    {
        $config = \is_string($settings) ? AnalysisConfig::fromJson($settings) : new AnalysisConfig($settings);

        return $config->analyzer($analyzer);
    }

    public function addCharacterFilter(CharacterFilterInterface $characterFilter): static
    {
        $this->characterFilters[] = $characterFilter;

        return $this;
    }

    public function addTokenizer(TokenizerInterface $tokenizer): static
    {
        $this->tokenizers[] = $tokenizer;

        return $this;
    }

    public function addTokenFilter(TokenFilterInterface $filter): static
    {
        $this->tokenFilters[] = $filter;

        return $this;
    }

    /**
     * @return list<CharacterFilterInterface>
     */
    public function getCharacterFilters(): array
    {
        return $this->characterFilters;
    }

    /**
     * @return list<TokenizerInterface>
     */
    public function getTokenizers(): array
    {
        return $this->tokenizers;
    }

    /**
     * @return list<TokenFilterInterface>
     */
    public function getTokenFilters(): array
    {
        return $this->tokenFilters;
    }

    /**
     * Analyzes $text and returns the terms.
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        return Tokens::terms($this->tokens($text));
    }

    /**
     * Analyzes $text and returns Token objects (term, position, offsets, type).
     * Offsets refer to the text after character filters.
     *
     * @return list<Token>
     */
    public function tokens(string $text): array
    {
        $tokens = $this->tokenizeText($this->filterCharacters($text));

        foreach ($this->tokenFilters as $filter) {
            $tokens = Tokens::normalize($filter->filter($tokens));
        }

        return $tokens;
    }

    /**
     * Debug helper showing the output of every step, similar to
     * Elasticsearch's `_analyze` API with `explain: true`.
     *
     * @return array{
     *     characterFilters: array<class-string, list<array{input: string, output: string}>>,
     *     tokenizers: array<class-string, list<array{input: string|list<string>, output: list<string>}>>,
     *     tokenFilters: array<class-string, list<array{input: list<string>, output: list<string>}>>,
     *     final: list<string>,
     *     tokens: list<array{token: string, start_offset: int|null, end_offset: int|null, type: string, position: int}>,
     * }
     */
    public function analyze(string $text): array
    {
        $result = ['characterFilters' => [], 'tokenizers' => [], 'tokenFilters' => []];

        foreach ($this->characterFilters as $filter) {
            $output = $filter->filter($text);
            $result['characterFilters'][$filter::class][] = ['input' => $text, 'output' => $output];
            $text = $output;
        }

        $tokens = [self::textToken($text)];
        $input = $text;
        foreach ($this->tokenizers as $tokenizer) {
            $tokens = $this->applyTokenizer($tokenizer, $tokens);
            $output = Tokens::terms($tokens);
            $result['tokenizers'][$tokenizer::class][] = ['input' => $input, 'output' => $output];
            $input = $output;
        }
        if ([] === $this->tokenizers && '' === $text) {
            $tokens = [];
        }

        foreach ($this->tokenFilters as $filter) {
            $before = Tokens::terms($tokens);
            $tokens = Tokens::normalize($filter->filter($tokens));
            $result['tokenFilters'][$filter::class][] = ['input' => $before, 'output' => Tokens::terms($tokens)];
        }

        $result['final'] = Tokens::terms($tokens);
        $result['tokens'] = array_map(static fn (Token $token): array => $token->toArray(), $tokens);

        return $result;
    }

    private function filterCharacters(string $text): string
    {
        foreach ($this->characterFilters as $filter) {
            $text = $filter->filter($text);
        }

        return $text;
    }

    /**
     * @return list<Token>
     */
    private function tokenizeText(string $text): array
    {
        if ([] === $this->tokenizers) {
            // No tokenizer: the whole text is a single token (keyword).
            return '' === $text ? [] : [self::textToken($text)];
        }

        $tokens = [self::textToken($text)];
        foreach ($this->tokenizers as $tokenizer) {
            $tokens = $this->applyTokenizer($tokenizer, $tokens);
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    private function applyTokenizer(TokenizerInterface $tokenizer, array $tokens): array
    {
        return $tokenizer instanceof AbstractTokenizer
            ? $tokenizer->tokenizeTokens($tokens)
            : Tokens::normalize($tokenizer->tokenize($tokens));
    }

    private static function textToken(string $text): Token
    {
        return new Token($text, 0, 0, mb_strlen($text));
    }

    /**
     * @template T of object
     *
     * @param T|iterable<T> $value
     *
     * @return iterable<T>
     */
    private static function iterate(mixed $value): iterable
    {
        return is_iterable($value) ? $value : [$value];
    }
}
