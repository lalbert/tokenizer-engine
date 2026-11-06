<?php

declare(strict_types=1);

namespace TokenizerEngine;

use TokenizerEngine\TokenFilter\AsciiFoldingTokenFilter;
use TokenizerEngine\TokenFilter\ElisionTokenFilter;
use TokenizerEngine\TokenFilter\EnglishPossessiveTokenFilter;
use TokenizerEngine\TokenFilter\FingerprintTokenFilter;
use TokenizerEngine\TokenFilter\GermanNormalizationTokenFilter;
use TokenizerEngine\TokenFilter\KeywordMarkerTokenFilter;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\StemmerOverrideTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;
use TokenizerEngine\TokenFilter\StopTokenFilter;
use TokenizerEngine\Tokenizer\KeywordTokenizer;
use TokenizerEngine\Tokenizer\LetterTokenizer;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\Tokenizer\WhitespaceTokenizer;

/**
 * Ready to use analyzers mirroring Elasticsearch's built-in analyzers.
 *
 * Language analyzers use the same filter chains as Elasticsearch, with
 * native ports of Lucene's stemmers (Porter, light French, German, Spanish,
 * Italian, Portuguese). The Dutch analyzer needs the wamania/php-stemmer
 * package (Snowball Dutch, like Elasticsearch).
 */
final class Analyzers
{
    public const LANGUAGES = ['english', 'french', 'german', 'spanish', 'italian', 'portuguese', 'dutch'];

    /**
     * Standard tokenizer + lowercase (+ optional stop words).
     *
     * @param string|list<string> $stopWords
     */
    public static function standard(string|array $stopWords = '_none_', int $maxTokenLength = 255): TokenizerEngine
    {
        $engine = (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer($maxTokenLength))
            ->addTokenFilter(new LowercaseTokenFilter());

        if ('_none_' !== $stopWords) {
            $engine->addTokenFilter(new StopTokenFilter($stopWords));
        }

        return $engine;
    }

    /**
     * Splits on non-letters and lowercases.
     */
    public static function simple(): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new LetterTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter());
    }

    public static function whitespace(): TokenizerEngine
    {
        return (new TokenizerEngine())->addTokenizer(new WhitespaceTokenizer());
    }

    public static function keyword(): TokenizerEngine
    {
        return (new TokenizerEngine())->addTokenizer(new KeywordTokenizer());
    }

    /**
     * Like simple, plus stop words.
     *
     * @param string|list<string> $stopWords
     */
    public static function stop(string|array $stopWords = '_english_', ?string $stopWordsPath = null): TokenizerEngine
    {
        return self::simple()->addTokenFilter(new StopTokenFilter($stopWords, $stopWordsPath));
    }

    /**
     * One normalized, sorted, deduplicated token per text: handy to detect
     * near duplicates ("Café de Paris" and "paris, cafe DE" give the same).
     *
     * @param string|list<string> $stopWords
     */
    public static function fingerprint(string $separator = ' ', int $maxOutputSize = 255, string|array $stopWords = '_none_'): TokenizerEngine
    {
        $engine = (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new AsciiFoldingTokenFilter());

        if ('_none_' !== $stopWords) {
            $engine->addTokenFilter(new StopTokenFilter($stopWords));
        }

        return $engine->addTokenFilter(new FingerprintTokenFilter($separator, $maxOutputSize));
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function english(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new EnglishPossessiveTokenFilter())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_english_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new StemmerTokenFilter('english'));
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function french(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new ElisionTokenFilter(ElisionTokenFilter::FRENCH_ARTICLES, true))
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_french_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new StemmerTokenFilter('light_french'));
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function italian(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new ElisionTokenFilter(ElisionTokenFilter::ITALIAN_ARTICLES, true))
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_italian_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new StemmerTokenFilter('light_italian'));
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function german(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_german_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new GermanNormalizationTokenFilter())
            ->addTokenFilter(new StemmerTokenFilter('light_german'));
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function spanish(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return self::lightLanguage('spanish', $stopWords, $stemExclusion);
    }

    /**
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function portuguese(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return self::lightLanguage('portuguese', $stopWords, $stemExclusion);
    }

    /**
     * Needs wamania/php-stemmer (Snowball Dutch), like Elasticsearch, with the
     * same default stem overrides (fiets, bromfiets, ei, kind).
     *
     * @param string|list<string>|null $stopWords     null = the language's default list
     * @param list<string>             $stemExclusion words protected from stemming
     */
    public static function dutch(string|array|null $stopWords = null, array $stemExclusion = []): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_dutch_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new StemmerOverrideTokenFilter(['fiets' => 'fiets', 'bromfiets' => 'bromfiets', 'ei' => 'eier', 'kind' => 'kinder']))
            ->addTokenFilter(new StemmerTokenFilter('dutch'));
    }

    /**
     * Returns a built-in analyzer by its Elasticsearch name.
     */
    public static function get(string $name): TokenizerEngine
    {
        return match ($name) {
            'standard', 'default' => self::standard(),
            'simple' => self::simple(),
            'whitespace' => self::whitespace(),
            'keyword' => self::keyword(),
            'stop' => self::stop(),
            'fingerprint' => self::fingerprint(),
            'english' => self::english(),
            'french' => self::french(),
            'german' => self::german(),
            'spanish' => self::spanish(),
            'italian' => self::italian(),
            'portuguese' => self::portuguese(),
            'dutch' => self::dutch(),
            default => throw new \InvalidArgumentException(\sprintf('Unknown analyzer "%s".', $name)),
        };
    }

    /**
     * @param string|list<string>|null $stopWords
     * @param list<string>             $stemExclusion
     */
    private static function lightLanguage(string $language, string|array|null $stopWords, array $stemExclusion): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_'.$language.'_'))
            ->addTokenFilter(new KeywordMarkerTokenFilter($stemExclusion))
            ->addTokenFilter(new StemmerTokenFilter('light_'.$language));
    }
}
