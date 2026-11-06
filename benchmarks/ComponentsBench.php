<?php

declare(strict_types=1);

namespace TokenizerEngine\Benchmarks;

use PhpBench\Attributes as Bench;
use TokenizerEngine\Chunker\TextChunker;
use TokenizerEngine\TokenFilter\EdgeNGramTokenFilter;
use TokenizerEngine\TokenFilter\StemmerTokenFilter;
use TokenizerEngine\TokenFilter\WordDelimiterTokenFilter;
use TokenizerEngine\Tokenizer\StandardTokenizer;

/**
 * Individual components on the tokens of 100 KB of French text.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(3)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class ComponentsBench
{
    private string $text;

    /**
     * @var list<string>
     */
    private array $words;

    public function setUp(): void
    {
        $this->text = Corpus::text(100);
        $this->words = (new StandardTokenizer())->tokenize(mb_strtolower($this->text));
    }

    public function benchStandardTokenizerIntl(): void
    {
        (new StandardTokenizer())->tokenize($this->text);
    }

    public function benchStandardTokenizerRegex(): void
    {
        (new StandardTokenizer(255, false))->tokenize($this->text);
    }

    public function benchWordDelimiter(): void
    {
        (new WordDelimiterTokenFilter(['catenateWords' => true]))->filter($this->words);
    }

    public function benchEdgeNGram(): void
    {
        (new EdgeNGramTokenFilter(1, 10))->filter($this->words);
    }

    public function benchLightFrenchStemmer(): void
    {
        (new StemmerTokenFilter('light_french'))->filter($this->words);
    }

    public function benchChunker(): void
    {
        (new TextChunker(1000, 200))->chunk($this->text);
    }
}
