<?php

declare(strict_types=1);

namespace TokenizerEngine\Stemmer;

use Wamania\Snowball\Stemmer\Stemmer;
use Wamania\Snowball\StemmerFactory;

/**
 * Snowball stemmers provided by the wamania/php-stemmer package.
 */
final class SnowballStemmer implements StemmerInterface
{
    private readonly Stemmer $stemmer;

    public function __construct(string $language)
    {
        if (!class_exists(StemmerFactory::class)) {
            throw new \LogicException('Snowball stemmers require the "wamania/php-stemmer" package: composer require wamania/php-stemmer');
        }

        try {
            $this->stemmer = StemmerFactory::create($language);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf('No stemmer available for language "%s".', $language), 0, $e);
        }
    }

    public function stem(string $word): string
    {
        // No Snowball suffix ends with a digit, so the reference
        // implementation (and Lucene) never changes such a word; some
        // wamania stemmers do (Finnish takes "00" in "sd500" for a double
        // consonant).
        if (preg_match('/\p{N}$/u', $word)) {
            return $word;
        }

        return $this->stemmer->stem($word);
    }
}
