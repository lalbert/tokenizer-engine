<?php

declare(strict_types=1);

namespace TokenizerEngine\Elasticsearch;

use TokenizerEngine\CharacterFilter as C;
use TokenizerEngine\CharacterFilter\CharacterFilterInterface;
use TokenizerEngine\TokenFilter as F;
use TokenizerEngine\TokenFilter\TokenFilterInterface;
use TokenizerEngine\Tokenizer as T;
use TokenizerEngine\Tokenizer\TokenizerInterface;
use TokenizerEngine\Util\WordList;

/**
 * Maps Elasticsearch component types ("lowercase", "edge_ngram"...) to
 * factories. Register your own components with the add*() methods.
 */
final class ComponentRegistry
{
    /**
     * @var array<string, \Closure(Params): CharacterFilterInterface>
     */
    private array $charFilters = [];

    /**
     * @var array<string, \Closure(Params): TokenizerInterface>
     */
    private array $tokenizers = [];

    /**
     * @var array<string, \Closure(Params): TokenFilterInterface>
     */
    private array $tokenFilters = [];

    public static function default(): self
    {
        $registry = new self();

        // Character filters
        $registry
            ->addCharFilter('html_strip', static fn (Params $p) => new C\HTMLStripCharacterFilter($p->list('escaped_tags', []) ?? []))
            ->addCharFilter('mapping', static fn (Params $p) => new C\MappingCharacterFilter($p->list('mappings', []) ?? [], $p->string('mappings_path')))
            ->addCharFilter('pattern_replace', static fn (Params $p) => new C\PatternReplaceCharacterFilter(
                (string) $p->string('pattern', ''),
                (string) $p->string('replacement', ''),
                self::javaFlags($p->string('flags', '')),
            ));

        // Tokenizers
        $registry
            ->addTokenizer('standard', static fn (Params $p) => new T\StandardTokenizer($p->int('max_token_length', 255)))
            ->addTokenizer('whitespace', static fn (Params $p) => new T\WhitespaceTokenizer($p->int('max_token_length', 255)))
            ->addTokenizer('keyword', static function (Params $p) {
                $p->int('buffer_size', 256);

                return new T\KeywordTokenizer();
            })
            ->addTokenizer('letter', static fn () => new T\LetterTokenizer())
            ->addTokenizer('pattern', static fn (Params $p) => new T\PatternTokenizer(
                (string) $p->string('pattern', '\W+'),
                self::javaFlags($p->string('flags', '')),
                $p->int('group', -1),
            ));

        // Token filters
        $registry
            ->addTokenFilter('lowercase', static fn () => new F\LowercaseTokenFilter())
            ->addTokenFilter('uppercase', static fn () => new F\UppercaseTokenFilter())
            ->addTokenFilter('trim', static fn () => new F\TrimTokenFilter())
            ->addTokenFilter('reverse', static fn () => new F\ReverseTokenFilter())
            ->addTokenFilter('apostrophe', static fn () => new F\ApostropheTokenFilter())
            ->addTokenFilter('asciifolding', static fn (Params $p) => new F\AsciiFoldingTokenFilter(null, $p->bool('preserve_original', false)))
            ->addTokenFilter('stop', static fn (Params $p) => new F\StopTokenFilter(
                $p->stringOrList('stopwords', '_english_'),
                $p->string('stopwords_path'),
                $p->bool('ignore_case', false),
                $p->bool('remove_trailing', true),
            ))
            ->addTokenFilter('elision', static function (Params $p) {
                $articles = $p->list('articles');
                if (null !== ($path = $p->string('articles_path'))) {
                    $articles = WordList::words($path);
                }

                return new F\ElisionTokenFilter($articles, $p->bool('articles_case', false));
            })
            ->addTokenFilter('ngram', $ngram = static fn (Params $p) => new F\NGramTokenFilter(
                $p->int('min_gram', 1),
                $p->int('max_gram', 2),
                $p->bool('preserve_original', false),
            ))
            ->addTokenFilter('nGram', $ngram)
            ->addTokenFilter('edge_ngram', $edge = static fn (Params $p) => new F\EdgeNGramTokenFilter(
                $p->int('min_gram', 1),
                $p->int('max_gram', 2),
                $p->bool('preserve_original', false),
            ))
            ->addTokenFilter('edgeNGram', $edge)
            ->addTokenFilter('truncate', static fn (Params $p) => new F\TruncateTokenFilter($p->int('length', 10)))
            ->addTokenFilter('limit', static fn (Params $p) => new F\LimitTokenCountTokenFilter($p->int('max_token_count', 1)))
            ->addTokenFilter('unique', static fn (Params $p) => new F\UniqueTokenFilter($p->bool('only_on_same_position', false)))
            ->addTokenFilter('length', static fn (Params $p) => new F\LengthTokenFilter($p->int('min', 0), $p->int('max', \PHP_INT_MAX)))
            ->addTokenFilter('fingerprint', static fn (Params $p) => new F\FingerprintTokenFilter(
                (string) $p->string('separator', ' '),
                $p->int('max_output_size', 255),
            ))
            ->addTokenFilter('word_delimiter', $wordDelimiter = static function (Params $p) {
                $protected = $p->list('protected_words', []) ?? [];
                if (null !== ($path = $p->string('protected_words_path'))) {
                    $protected = array_merge($protected, WordList::words($path));
                }

                return new F\WordDelimiterTokenFilter([
                    'generateWordParts' => $p->bool('generate_word_parts', true),
                    'generateNumberParts' => $p->bool('generate_number_parts', true),
                    'catenateWords' => $p->bool('catenate_words', false),
                    'catenateNumbers' => $p->bool('catenate_numbers', false),
                    'catenateAll' => $p->bool('catenate_all', false),
                    'splitOnCaseChange' => $p->bool('split_on_case_change', true),
                    'preserveOriginal' => $p->bool('preserve_original', false),
                    'splitOnNumerics' => $p->bool('split_on_numerics', true),
                    'stemEnglishPossessive' => $p->bool('stem_english_possessive', true),
                    'protectedWords' => $protected,
                ]);
            })
            ->addTokenFilter('word_delimiter_graph', $wordDelimiter)
            ->addTokenFilter('stemmer', static function (Params $p) {
                $language = (string) ($p->string('language') ?? $p->string('name', 'english'));

                return 'possessive_english' === $language
                    ? new F\EnglishPossessiveTokenFilter()
                    : new F\StemmerTokenFilter($language);
            })
            ->addTokenFilter('snowball', static fn (Params $p) => new F\StemmerTokenFilter((string) $p->string('language', 'english')))
            ->addTokenFilter('porter_stem', static fn () => new F\StemmerTokenFilter('english'))
            ->addTokenFilter('synonym', $synonym = static function (Params $p) {
                $p->bool('lenient', false);
                $p->bool('updateable', false);
                $p->string('format', 'solr');

                return new F\SynonymTokenFilter(
                    $p->list('synonyms', []) ?? [],
                    $p->string('synonyms_path'),
                    $p->bool('expand', true),
                    $p->bool('ignore_case', false),
                );
            })
            ->addTokenFilter('synonym_graph', $synonym)
            // Not an Elasticsearch filter, provided by this library.
            ->addTokenFilter('levenshtein', static fn (Params $p) => new F\LevenshteinTokenFilter(
                $p->list('words', []) ?? [],
                $p->int('max_distance', \PHP_INT_MAX),
            ));

        return $registry;
    }

    /**
     * @param callable(Params): CharacterFilterInterface $factory
     */
    public function addCharFilter(string $type, callable $factory): self
    {
        $this->charFilters[$type] = $factory(...);

        return $this;
    }

    /**
     * @param callable(Params): TokenizerInterface $factory
     */
    public function addTokenizer(string $type, callable $factory): self
    {
        $this->tokenizers[$type] = $factory(...);

        return $this;
    }

    /**
     * @param callable(Params): TokenFilterInterface $factory
     */
    public function addTokenFilter(string $type, callable $factory): self
    {
        $this->tokenFilters[$type] = $factory(...);

        return $this;
    }

    public function hasCharFilter(string $type): bool
    {
        return isset($this->charFilters[$type]);
    }

    public function hasTokenizer(string $type): bool
    {
        return isset($this->tokenizers[$type]);
    }

    public function hasTokenFilter(string $type): bool
    {
        return isset($this->tokenFilters[$type]);
    }

    public function createCharFilter(string $type, Params $params): CharacterFilterInterface
    {
        return ($this->charFilters[$type] ?? throw self::unknown('char_filter', $type))($params);
    }

    public function createTokenizer(string $type, Params $params): TokenizerInterface
    {
        return ($this->tokenizers[$type] ?? throw self::unknown('tokenizer', $type))($params);
    }

    public function createTokenFilter(string $type, Params $params): TokenFilterInterface
    {
        return ($this->tokenFilters[$type] ?? throw self::unknown('filter', $type))($params);
    }

    /**
     * Converts Java Pattern flags ("CASE_INSENSITIVE|COMMENTS") to PCRE modifiers.
     */
    private static function javaFlags(?string $flags): string
    {
        $map = ['CASE_INSENSITIVE' => 'i', 'COMMENTS' => 'x', 'DOTALL' => 's', 'MULTILINE' => 'm'];
        $modifiers = '';

        foreach (preg_split('/\s*\|\s*/', (string) $flags, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $flag) {
            $modifiers .= $map[strtoupper($flag)] ?? '';
        }

        return $modifiers;
    }

    private static function unknown(string $kind, string $type): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf('Unknown %s type "%s".', $kind, $type));
    }
}
