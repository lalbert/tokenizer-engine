<?php

declare(strict_types=1);

namespace TokenizerEngine;

use TokenizerEngine\TokenFilter\AsciiFoldingTokenFilter;
use TokenizerEngine\TokenFilter\ElisionTokenFilter;
use TokenizerEngine\TokenFilter\EnglishPossessiveTokenFilter;
use TokenizerEngine\TokenFilter\FingerprintTokenFilter;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;
use TokenizerEngine\TokenFilter\StopTokenFilter;
use TokenizerEngine\Tokenizer\KeywordTokenizer;
use TokenizerEngine\Tokenizer\LetterTokenizer;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\Tokenizer\WhitespaceTokenizer;

/**
 * Ready to use analyzers mirroring Elasticsearch's built-in analyzers.
 *
 * Language analyzers need the wamania/php-stemmer package. They use Snowball
 * stemmers, which are close to but not identical with the "light" stemmers
 * Elasticsearch uses for some languages (French, German, Spanish...).
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
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function english(string|array|null $stopWords = null): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new EnglishPossessiveTokenFilter())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_english_'))
            ->addTokenFilter(new StemmerTokenFilter('english'));
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function french(string|array|null $stopWords = null): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new ElisionTokenFilter(ElisionTokenFilter::FRENCH_ARTICLES, true))
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_french_'))
            ->addTokenFilter(new StemmerTokenFilter('french'));
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function italian(string|array|null $stopWords = null): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new ElisionTokenFilter(ElisionTokenFilter::ITALIAN_ARTICLES, true))
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_italian_'))
            ->addTokenFilter(new StemmerTokenFilter('italian'));
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function german(string|array|null $stopWords = null): TokenizerEngine
    {
        return self::basicLanguage('german', $stopWords);
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function spanish(string|array|null $stopWords = null): TokenizerEngine
    {
        return self::basicLanguage('spanish', $stopWords);
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function portuguese(string|array|null $stopWords = null): TokenizerEngine
    {
        return self::basicLanguage('portuguese', $stopWords);
    }

    /**
     * @param string|list<string>|null $stopWords null = the language's default list
     */
    public static function dutch(string|array|null $stopWords = null): TokenizerEngine
    {
        return self::basicLanguage('dutch', $stopWords);
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
     */
    private static function basicLanguage(string $language, string|array|null $stopWords): TokenizerEngine
    {
        return (new TokenizerEngine())
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter())
            ->addTokenFilter(new StopTokenFilter($stopWords ?? '_'.$language.'_'))
            ->addTokenFilter(new StemmerTokenFilter($language));
    }
}
