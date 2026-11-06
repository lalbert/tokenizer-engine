<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Stemmer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Stemmer\GermanLightStemmer;
use TokenizerEngine\Stemmer\ItalianLightStemmer;
use TokenizerEngine\Stemmer\PortugueseLightStemmer;
use TokenizerEngine\Stemmer\SpanishLightStemmer;
use TokenizerEngine\Stemmer\StemmerInterface;
use TokenizerEngine\TokenFilter\GermanNormalizationTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;

/**
 * Checks the ports against samples of Apache Lucene's test vocabularies
 * (the full vocabularies, about 131,000 words, were checked during the port).
 */
final class LightStemmersTest extends TestCase
{
    /**
     * @return iterable<string, array{StemmerInterface, string}>
     */
    public static function vocabularies(): iterable
    {
        yield 'german' => [new GermanLightStemmer(), 'german-light-stemmer.tsv'];
        yield 'spanish' => [new SpanishLightStemmer(), 'spanish-light-stemmer.tsv'];
        yield 'italian' => [new ItalianLightStemmer(), 'italian-light-stemmer.tsv'];
        yield 'portuguese' => [new PortugueseLightStemmer(), 'portuguese-light-stemmer.tsv'];
    }

    #[DataProvider('vocabularies')]
    public function testLuceneVocabulary(StemmerInterface $stemmer, string $file): void
    {
        $failures = [];
        $count = 0;

        foreach (file(__DIR__.'/../../Fixtures/'.$file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }
            [$word, $stem] = explode("\t", $line);
            ++$count;
            if ($stemmer->stem($word) !== $stem) {
                $failures[] = $word;
            }
        }

        self::assertGreaterThan(1000, $count);
        self::assertSame([], $failures);
    }

    public function testElasticsearchNames(): void
    {
        self::assertSame(['katz'], (new StemmerTokenFilter('light_german'))->filter(['katzen']));
        // Values returned by Elasticsearch 9.5.
        self::assertSame(['niñ'], (new StemmerTokenFilter('light_spanish'))->filter(['niños']));
        self::assertSame(['bibliotec'], (new StemmerTokenFilter('light_italian'))->filter(['biblioteche']));
        self::assertSame(['crianc'], (new StemmerTokenFilter('light_portuguese'))->filter(['crianças']));
    }

    public function testGermanNormalization(): void
    {
        self::assertSame(['haus', 'grosse', 'muller', 'muller', 'blau'], (new GermanNormalizationTokenFilter())->filter(['häus', 'große', 'müller', 'mueller', 'blau']));
    }
}
