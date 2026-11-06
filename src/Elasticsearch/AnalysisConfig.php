<?php

declare(strict_types=1);

namespace TokenizerEngine\Elasticsearch;

use TokenizerEngine\Analyzers;
use TokenizerEngine\Normalizer;
use TokenizerEngine\TokenFilter as F;
use TokenizerEngine\TokenFilter\TokenFilterInterface;
use TokenizerEngine\Tokenizer as T;
use TokenizerEngine\TokenizerEngine;

/**
 * Builds analyzers from the `analysis` section of Elasticsearch index
 * settings, so the same configuration can be used in PHP and in the cluster.
 *
 * Accepted shapes: the full index settings ({"settings": {"analysis": ...}}),
 * {"settings": {"index": {"analysis": ...}}}, {"analysis": ...} or the
 * analysis object itself ({"analyzer": ..., "filter": ...}).
 *
 * In strict mode, parameters this library does not support raise an
 * exception instead of being ignored.
 */
final class AnalysisConfig
{
    /**
     * Components Elasticsearch accepts in normalizers (they work character by
     * character); others can be allowed with allowInNormalizers().
     */
    private const NORMALIZER_CHAR_FILTERS = ['mapping', 'pattern_replace', 'icu_normalizer'];
    private const NORMALIZER_FILTERS = ['asciifolding', 'decimal_digit', 'elision', 'german_normalization', 'lowercase', 'pattern_replace', 'trim', 'uppercase', 'icu_normalizer', 'icu_folding'];

    /**
     * @var array<string, true>
     */
    private array $extraNormalizerTypes = [];

    private const BUILTIN_ANALYZER_TYPES = ['standard', 'simple', 'whitespace', 'keyword', 'stop', 'fingerprint', 'pattern', ...Analyzers::LANGUAGES];

    /**
     * @var array<string, mixed>
     */
    private readonly array $analysis;

    private readonly ComponentRegistry $registry;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings, ?ComponentRegistry $registry = null, private readonly bool $strict = false)
    {
        $this->analysis = self::locateAnalysis($settings);
        $this->registry = $registry ?? ComponentRegistry::default();
    }

    public static function fromJson(string $json, ?ComponentRegistry $registry = null, bool $strict = false): self
    {
        $settings = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($settings)) {
            throw new \InvalidArgumentException('Elasticsearch settings must be a JSON object.');
        }

        /** @var array<string, mixed> $settings */
        return new self($settings, $registry, $strict);
    }

    public static function fromFile(string $path, ?ComponentRegistry $registry = null, bool $strict = false): self
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException(\sprintf('Filename "%s" is not readable.', $path));
        }

        return self::fromJson((string) file_get_contents($path), $registry, $strict);
    }

    /**
     * @return list<string> names of the analyzers defined in the settings
     */
    public function analyzerNames(): array
    {
        return array_map('strval', array_keys($this->section('analyzer')));
    }

    /**
     * @return list<string> names of the normalizers defined in the settings
     */
    public function normalizerNames(): array
    {
        return array_map('strval', array_keys($this->section('normalizer')));
    }

    /**
     * Allows custom component types (registered in the ComponentRegistry) in
     * normalizers.
     */
    public function allowInNormalizers(string ...$types): self
    {
        foreach ($types as $type) {
            $this->extraNormalizerTypes[$type] = true;
        }

        return $this;
    }

    /**
     * Builds a normalizer ("lowercase" is built in, like in Elasticsearch).
     */
    public function normalizer(string $name): Normalizer
    {
        $definitions = $this->section('normalizer');

        if (!isset($definitions[$name])) {
            if ('lowercase' === $name) {
                return new Normalizer((new TokenizerEngine())->addTokenizer(new T\KeywordTokenizer())->addTokenFilter(new F\LowercaseTokenFilter()));
            }

            throw new \InvalidArgumentException(\sprintf('Unknown normalizer "%s".', $name));
        }

        $definition = self::definition($definitions[$name], 'normalizer', $name);
        if ('custom' !== ($definition['type'] ?? 'custom')) {
            throw new \InvalidArgumentException(\sprintf('Normalizer "%s" must be of type "custom".', $name));
        }

        $engine = (new TokenizerEngine())->addTokenizer(new T\KeywordTokenizer());

        foreach ($this->references($definition['char_filter'] ?? []) as $reference) {
            [$type, $params] = $this->resolve('char_filter', $reference);
            $this->assertNormalizerType($name, 'char_filter', $type, self::NORMALIZER_CHAR_FILTERS);
            $engine->addCharacterFilter($this->registry->createCharFilter($type, $params));
            $this->checkUnused($params);
        }

        foreach ($this->references($definition['filter'] ?? []) as $reference) {
            [$type, $params] = $this->resolve('filter', $reference);
            $this->assertNormalizerType($name, 'filter', $type, self::NORMALIZER_FILTERS);
            $engine->addTokenFilter($this->registry->createTokenFilter($type, $params));
            $this->checkUnused($params);
        }

        $this->checkUnused(new Params(array_diff_key($definition, array_flip(['type', 'char_filter', 'filter'])), \sprintf('normalizer "%s"', $name)));

        return new Normalizer($engine);
    }

    /**
     * @param list<string> $allowed
     */
    private function assertNormalizerType(string $normalizer, string $kind, string $type, array $allowed): void
    {
        if (!\in_array($type, $allowed, true) && !isset($this->extraNormalizerTypes[$type])) {
            throw new \InvalidArgumentException(\sprintf('The %s "%s" cannot be used in normalizer "%s": only components working character by character are allowed (%s).', $kind, $type, $normalizer, implode(', ', $allowed)));
        }
    }

    public function analyzer(string $name = 'default'): TokenizerEngine
    {
        $definitions = $this->section('analyzer');

        if (!isset($definitions[$name])) {
            return 'default' === $name ? Analyzers::standard() : Analyzers::get($name);
        }

        $definition = self::definition($definitions[$name], 'analyzer', $name);
        $type = (string) ($definition['type'] ?? 'custom');

        return 'custom' === $type
            ? $this->custom($name, $definition)
            : $this->builtin($name, $type, $definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function custom(string $name, array $definition): TokenizerEngine
    {
        if (!isset($definition['tokenizer'])) {
            throw new \InvalidArgumentException(\sprintf('Analyzer "%s" must define a tokenizer.', $name));
        }

        $engine = new TokenizerEngine();

        foreach ($this->references($definition['char_filter'] ?? []) as $reference) {
            [$type, $params] = $this->resolve('char_filter', $reference);
            $engine->addCharacterFilter($this->registry->createCharFilter($type, $params));
            $this->checkUnused($params);
        }

        [$type, $params] = $this->resolve('tokenizer', $definition['tokenizer']);
        $engine->addTokenizer($this->registry->createTokenizer($type, $params));
        $this->checkUnused($params);

        foreach ($this->references($definition['filter'] ?? []) as $reference) {
            [$type, $params] = $this->resolve('filter', $reference);
            $engine->addTokenFilter($this->registry->createTokenFilter($type, $params));
            $this->checkUnused($params);
        }

        $this->checkUnused(new Params(array_diff_key($definition, array_flip(['type', 'tokenizer', 'char_filter', 'filter', 'position_increment_gap'])), \sprintf('analyzer "%s"', $name)));

        return $engine;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function builtin(string $name, string $type, array $definition): TokenizerEngine
    {
        if (!\in_array($type, self::BUILTIN_ANALYZER_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown analyzer type "%s" for analyzer "%s".', $type, $name));
        }

        $p = new Params($definition, \sprintf('analyzer "%s"', $name));
        $stopWords = $p->stringOrList('stopwords');
        if (null !== ($path = $p->string('stopwords_path'))) {
            $stopWords = (new F\StopTokenFilter(null, $path))->getStopWords();
        }

        $stemExclusion = $p->list('stem_exclusion', []) ?? [];

        $engine = match ($type) {
            'standard' => Analyzers::standard($stopWords ?? '_none_', $p->int('max_token_length', 255)),
            'stop' => Analyzers::stop($stopWords ?? '_english_'),
            'fingerprint' => Analyzers::fingerprint((string) $p->string('separator', ' '), $p->int('max_output_size', 255), $stopWords ?? '_none_'),
            'pattern' => $this->patternAnalyzer($p, $stopWords),
            'english' => Analyzers::english($stopWords, $stemExclusion),
            'french' => Analyzers::french($stopWords, $stemExclusion),
            'german' => Analyzers::german($stopWords, $stemExclusion),
            'spanish' => Analyzers::spanish($stopWords, $stemExclusion),
            'italian' => Analyzers::italian($stopWords, $stemExclusion),
            'portuguese' => Analyzers::portuguese($stopWords, $stemExclusion),
            'dutch' => Analyzers::dutch($stopWords, $stemExclusion),
            default => Analyzers::get($type),
        };

        $this->checkUnused($p);

        return $engine;
    }

    /**
     * @param string|list<string>|null $stopWords
     */
    private function patternAnalyzer(Params $p, string|array|null $stopWords): TokenizerEngine
    {
        $engine = (new TokenizerEngine())->addTokenizer(new T\PatternTokenizer((string) $p->string('pattern', '\W+')));

        if ($p->bool('lowercase', true)) {
            $engine->addTokenFilter(new F\LowercaseTokenFilter());
        }

        if (null !== $stopWords && '_none_' !== $stopWords) {
            $engine->addTokenFilter(new F\StopTokenFilter($stopWords));
        }

        return $engine;
    }

    /**
     * @return array{0: string, 1: Params}
     */
    private function resolve(string $kind, mixed $reference): array
    {
        if (\is_array($reference)) {
            /** @var array<string, mixed> $reference */
            $type = (string) ($reference['type'] ?? throw new \InvalidArgumentException(\sprintf('Inline %s definitions must have a "type".', $kind)));

            return [$type, new Params($reference, \sprintf('%s "%s"', $kind, $type), $this->filterResolver())];
        }

        $name = (string) $reference;
        $definitions = $this->section($kind);

        if (isset($definitions[$name])) {
            $definition = self::definition($definitions[$name], $kind, $name);
            $type = (string) ($definition['type'] ?? throw new \InvalidArgumentException(\sprintf('The %s "%s" must have a "type".', $kind, $name)));

            return [$type, new Params($definition, \sprintf('%s "%s"', $kind, $name), $this->filterResolver())];
        }

        return [$name, new Params([], \sprintf('%s "%s"', $kind, $name), $this->filterResolver())];
    }

    /**
     * @return \Closure(string): TokenFilterInterface
     */
    private function filterResolver(): \Closure
    {
        return function (string $name): TokenFilterInterface {
            [$type, $params] = $this->resolve('filter', $name);
            $filter = $this->registry->createTokenFilter($type, $params);
            $this->checkUnused($params);

            return $filter;
        };
    }

    /**
     * @return list<mixed>
     */
    private function references(mixed $value): array
    {
        if (\is_string($value) || (\is_array($value) && isset($value['type']))) {
            return [$value];
        }

        return \is_array($value) ? array_values($value) : [];
    }

    private function checkUnused(Params $params): void
    {
        if ($this->strict && [] !== ($unused = $params->unused())) {
            throw new \InvalidArgumentException(\sprintf('Unsupported parameter(s): %s.', implode(', ', $unused)));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function section(string $name): array
    {
        $section = $this->analysis[$name] ?? [];

        return \is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function definition(mixed $definition, string $kind, string $name): array
    {
        if (!\is_array($definition)) {
            throw new \InvalidArgumentException(\sprintf('The %s "%s" must be an object.', $kind, $name));
        }

        /** @var array<string, mixed> $definition */
        return $definition;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function locateAnalysis(array $settings): array
    {
        foreach ([['settings', 'index', 'analysis'], ['settings', 'analysis'], ['index', 'analysis'], ['analysis']] as $path) {
            $node = $settings;
            foreach ($path as $key) {
                if (!\is_array($node) || !isset($node[$key])) {
                    continue 2;
                }
                $node = $node[$key];
            }

            if (\is_array($node)) {
                /** @var array<string, mixed> $node */
                return $node;
            }
        }

        return $settings;
    }
}
