<?php

declare(strict_types=1);

namespace TokenizerEngine\Benchmarks;

use PhpBench\Attributes as Bench;
use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\Highlighting\TermMatcher;
use TokenizerEngine\TokenFilter\LowercaseTokenFilter;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\TokenizerEngine;

/**
 * html_strip, offset mapping and TermMatcher on 100 KB of HTML.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(3)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class HtmlBench
{
    private string $html;
    private HTMLStripCharacterFilter $strip;
    private TokenizerEngine $engine;
    private TermMatcher $matcher;

    public function setUp(): void
    {
        $this->html = Corpus::html(100);
        $this->strip = new HTMLStripCharacterFilter();
        $this->engine = (new TokenizerEngine())
            ->addCharacterFilter($this->strip)
            ->addTokenizer(new StandardTokenizer())
            ->addTokenFilter(new LowercaseTokenFilter());
        $this->matcher = new TermMatcher($this->engine);
    }

    public function benchStripOnly(): void
    {
        $this->strip->filter($this->html);
    }

    public function benchStripWithOffsets(): void
    {
        $this->strip->filterWithOffsets($this->html);
    }

    public function benchTokensWithOffsetsMappedToHtml(): void
    {
        $this->engine->tokens($this->html);
    }

    public function benchTermMatcher(): void
    {
        $this->matcher->match($this->html, 'école montréal chevaux');
    }
}
