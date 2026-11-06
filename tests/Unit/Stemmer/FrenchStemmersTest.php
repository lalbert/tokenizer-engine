<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Stemmer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Stemmer\FrenchLightStemmer;
use TokenizerEngine\Stemmer\FrenchMinimalStemmer;
use TokenizerEngine\Stemmer\StemmerInterface;

/**
 * Checks the ports against the test cases of Apache Lucene.
 */
final class FrenchStemmersTest extends TestCase
{
    /**
     * @return iterable<string, array{StemmerInterface, string, string}>
     */
    public static function cases(): iterable
    {
        foreach (['french-light-stemmer.tsv' => new FrenchLightStemmer(), 'french-minimal-stemmer.tsv' => new FrenchMinimalStemmer()] as $file => $stemmer) {
            foreach (file(__DIR__.'/../../Fixtures/'.$file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with($line, '#')) {
                    continue;
                }
                [$word, $stem] = explode("\t", $line);
                yield $file.': '.$word => [$stemmer, $word, $stem];
            }
        }
    }

    #[DataProvider('cases')]
    public function testLuceneCases(StemmerInterface $stemmer, string $word, string $expected): void
    {
        self::assertSame($expected, $stemmer->stem($word));
    }
}
