<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use Wamania\Snowball\Stemmer\Stemmer;
use Wamania\Snowball\StemmerFactory;

/**
 * Reduces words to their stem ("chevaux" becomes "cheval"), using the
 * Snowball algorithms of the wamania/php-stemmer package.
 *
 * Accepts Snowball language names and ISO codes (french, fr, english, en...)
 * plus the Elasticsearch stemmer names that map to them (light_french,
 * porter, porter2, light_german, ...). Note that Elasticsearch's "light_*"
 * and "minimal_*" stemmers are different algorithms: results can differ.
 *
 * Terms are expected to be lowercased first.
 */
final class StemmerTokenFilter extends AbstractTermTokenFilter
{
    private const ALIASES = [
        'porter' => 'english', 'porter2' => 'english', 'light_english' => 'english', 'minimal_english' => 'english', 'lovins' => 'english',
        'light_french' => 'french', 'minimal_french' => 'french',
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

    private readonly Stemmer $stemmer;

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(string $language = 'english')
    {
        if (!class_exists(StemmerFactory::class)) {
            throw new \LogicException('StemmerTokenFilter requires the "wamania/php-stemmer" package: composer require wamania/php-stemmer');
        }

        $language = strtolower($language);

        if ('possessive_english' === $language) {
            throw new \InvalidArgumentException('Use EnglishPossessiveTokenFilter for "possessive_english".');
        }

        try {
            $this->stemmer = StemmerFactory::create(self::ALIASES[$language] ?? $language);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf('No stemmer available for language "%s".', $language), 0, $e);
        }
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
