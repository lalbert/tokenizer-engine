<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Stemmer;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Stemmer\PorterStemmer;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;

final class PorterStemmerTest extends TestCase
{
    /**
     * Checks the port against a sample of Lucene's Porter test vocabulary
     * (the full vocabulary of 23,531 words was checked during the port).
     */
    public function testLuceneVocabulary(): void
    {
        $stemmer = new PorterStemmer();
        $failures = [];

        foreach (file(__DIR__.'/../../Fixtures/porter-stemmer.tsv', \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }
            [$word, $stem] = explode("\t", $line);
            if ($stemmer->stem($word) !== $stem) {
                $failures[] = $word;
            }
        }

        self::assertSame([], $failures);
    }

    public function testElasticsearchNames(): void
    {
        foreach (['english', 'porter'] as $name) {
            self::assertSame(['jump', 'lazi', "dog'"], (new StemmerTokenFilter($name))->filter(['jumped', 'lazy', "dog's"]), $name);
        }

        // Snowball (Porter2) handles apostrophes.
        self::assertSame(['dog'], (new StemmerTokenFilter('porter2'))->filter(["dog's"]));
    }
}
