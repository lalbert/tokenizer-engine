<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Chunker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Analyzers;
use TokenizerEngine\Chunker\Chunk;
use TokenizerEngine\Chunker\TextChunker;

final class TextChunkerTest extends TestCase
{
    private const TEXT = "Première phrase du texte. Deuxième phrase, un peu plus longue que la première ! Troisième ?\n\nNouveau paragraphe ici. Et une dernière phrase pour finir.";

    /**
     * @return iterable<string, array{bool}>
     */
    public static function modes(): iterable
    {
        yield 'intl' => [true];
        yield 'regex fallback' => [false];
    }

    #[DataProvider('modes')]
    public function testSentenceBoundariesAndOverlap(bool $useIntl): void
    {
        $chunks = (new TextChunker(60, 30, null, 'fr', $useIntl))->chunk(self::TEXT);

        self::assertSame(
            [
                'Première phrase du texte.',
                'Deuxième phrase, un peu plus longue que la première !',
                "Troisième ?\n\nNouveau paragraphe ici.",
                'Nouveau paragraphe ici. Et une dernière phrase pour finir.',
            ],
            array_map(static fn (Chunk $c): string => $c->text, $chunks),
        );
        self::assertSame([0, 1, 2, 3], array_map(static fn (Chunk $c): int => $c->index, $chunks));
    }

    #[DataProvider('modes')]
    public function testOffsetsPointToTheOriginalText(bool $useIntl): void
    {
        foreach ((new TextChunker(40, 10, null, 'fr', $useIntl))->chunk(self::TEXT) as $chunk) {
            self::assertSame($chunk->text, mb_substr(self::TEXT, $chunk->startOffset, $chunk->endOffset - $chunk->startOffset));
            self::assertLessThanOrEqual(40, $chunk->size);
        }
    }

    public function testWithoutOverlapEveryCharacterIsCovered(): void
    {
        $chunks = (new TextChunker(30))->chunk(self::TEXT);

        self::assertSame(
            (string) preg_replace('/\s+/u', '', self::TEXT),
            (string) preg_replace('/\s+/u', '', implode('', array_map(static fn (Chunk $c): string => $c->text, $chunks))),
        );
    }

    public function testLongSentencesAreSplitOnWordsThenCharacters(): void
    {
        $chunks = (new TextChunker(10))->chunk('aaaa bbbb cccc dddddddddddddddddddddd');

        self::assertSame(['aaaa bbbb', 'cccc', 'dddddddddd', 'dddddddddd', 'dd'], array_map(static fn (Chunk $c): string => $c->text, $chunks));
    }

    public function testTokenSizer(): void
    {
        $chunks = TextChunker::byTokens(Analyzers::standard(), 5)->chunk('un deux trois quatre cinq six sept huit neuf dix onze');

        self::assertSame(['un deux trois quatre cinq', 'six sept huit neuf dix', 'onze'], array_map(static fn (Chunk $c): string => $c->text, $chunks));
        self::assertSame([5, 5, 1], array_map(static fn (Chunk $c): int => $c->size, $chunks));
    }

    public function testEmptyText(): void
    {
        self::assertSame([], (new TextChunker())->chunk(''));
        self::assertSame([], (new TextChunker())->chunk("  \n "));
    }

    public function testInvalidOverlap(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextChunker(10, 10);
    }

    public function testToArray(): void
    {
        self::assertSame(['index' => 0, 'text' => 'Bonjour.', 'start_offset' => 0, 'end_offset' => 8, 'size' => 8], (new TextChunker())->chunk('Bonjour.')[0]->toArray());
    }
}
