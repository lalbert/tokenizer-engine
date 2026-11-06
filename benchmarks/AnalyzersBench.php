<?php

declare(strict_types=1);

namespace TokenizerEngine\Benchmarks;

use PhpBench\Attributes as Bench;
use TokenizerEngine\Analyzers;
use TokenizerEngine\TokenizerEngine;

/**
 * Built-in analyzers on 100 KB of French text.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(3)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class AnalyzersBench
{
    private string $text;
    private TokenizerEngine $standard;
    private TokenizerEngine $french;
    private TokenizerEngine $english;
    private TokenizerEngine $fingerprint;

    public function setUp(): void
    {
        $this->text = Corpus::text(100);
        $this->standard = Analyzers::standard();
        $this->french = Analyzers::french();
        $this->english = Analyzers::english();
        $this->fingerprint = Analyzers::fingerprint(maxOutputSize: 1_000_000);
    }

    public function benchStandard(): void
    {
        $this->standard->tokenize($this->text);
    }

    public function benchFrench(): void
    {
        $this->french->tokenize($this->text);
    }

    public function benchEnglish(): void
    {
        $this->english->tokenize($this->text);
    }

    public function benchFingerprint(): void
    {
        $this->fingerprint->tokenize($this->text);
    }

    public function benchFrenchTokensWithOffsets(): void
    {
        $this->french->tokens($this->text);
    }
}
