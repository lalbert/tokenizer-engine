<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Stemmer\FrenchLightStemmer;
use TokenizerEngine\Stemmer\FrenchMinimalStemmer;
use TokenizerEngine\Stemmer\SnowballStemmer;
use TokenizerEngine\Stemmer\StemmerInterface;
use TokenizerEngine\Token;

/**
 * Reduces words to their stem ("chevaux" becomes "cheval"), like
 * Elasticsearch's `stemmer` filter. Tokens flagged as keywords (see
 * KeywordMarkerTokenFilter) are left untouched.
 *
 * - `light_french` and `minimal_french` are native ports of the Lucene
 *   stemmers used by Elasticsearch (no dependency);
 * - other languages use the Snowball algorithms of the wamania/php-stemmer
 *   package (french, english, german...). Elasticsearch names that map to a
 *   different algorithm (porter, light_german...) fall back to Snowball, so
 *   results can differ from Elasticsearch for them.
 *
 * Terms are expected to be lowercased first.
 */
final class StemmerTokenFilter extends AbstractTermTokenFilter
{
    private const ALIASES = [
        'porter' => 'english', 'porter2' => 'english', 'light_english' => 'english', 'minimal_english' => 'english', 'lovins' => 'english',
        'light_german' => 'german', 'german2' => 'german', 'minimal_german' => 'german',
        'light_spanish' => 'spanish',
        'light_italian' => 'italian',
        'light_portuguese' => 'portuguese', 'minimal_portuguese' => 'portuguese', 'portuguese_rslp' => 'portuguese',
        'dutch_kp' => 'dutch',
        'light_finnish' => 'finnish',
        'light_swedish' => 'swedish',
        'light_norwegian' => 'norwegian', 'minimal_norwegian' => 'norwegian',
        'light_russian' => 'russian',
    ];

    private readonly StemmerInterface $stemmer;

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(string|StemmerInterface $language = 'english')
    {
        $this->stemmer = $language instanceof StemmerInterface ? $language : self::create($language);
    }

    public static function create(string $language): StemmerInterface
    {
        $language = strtolower($language);

        return match ($language) {
            'light_french' => new FrenchLightStemmer(),
            'minimal_french' => new FrenchMinimalStemmer(),
            'possessive_english' => throw new \InvalidArgumentException('Use EnglishPossessiveTokenFilter for "possessive_english".'),
            default => new SnowballStemmer(self::ALIASES[$language] ?? $language),
        };
    }

    protected function skipKeywords(): bool
    {
        return true;
    }

    protected function filterTerm(string $term, Token $token): string
    {
        if ('' === $term) {
            return $term;
        }

        if (\count($this->cache) > 10000) {
            $this->cache = [];
        }

        return $this->cache[$term] ??= $this->stemmer->stem($term);
    }
}
