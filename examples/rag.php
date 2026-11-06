<?php

declare(strict_types=1);

/*
 * Retrieval augmented generation (RAG) without a vector database:
 *   1. split the documents into passages with TextChunker;
 *   2. index the passages in plain SQL (examples/SqlIndex.php, BM25);
 *   3. retrieve the best passages for a question;
 *   4. build a prompt that cites its sources, for any LLM.
 *
 * Keyword retrieval works well on precise questions (names, places, terms);
 * for paraphrases, combine it with embeddings (hybrid search): the passages
 * and their offsets are the same.
 *
 * Run: php examples/rag.php ["your question"]
 */

use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\Chunker\TextChunker;
use TokenizerEngine\Examples\SqlIndex;
use TokenizerEngine\TokenizerEngine;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/SqlIndex.php';

$french = TokenizerEngine::fromElasticsearch(['analysis' => [
    'filter' => [
        'french_elision' => ['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']],
        'french_stop' => ['type' => 'stop', 'stopwords' => '_french_'],
        'french_stemmer' => ['type' => 'stemmer', 'language' => 'light_french'],
    ],
    'analyzer' => ['french' => ['tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'french_stop', 'french_stemmer']]],
]], 'french');

// 1. Passages of at most 150 characters, cut at sentence boundaries, with 40
//    characters of overlap. Sizes can also be counted in tokens
//    (TextChunker::byTokens()) or with any callable, e.g. an LLM tokenizer.
$chunker = new TextChunker(150, 40, locale: 'fr');
$strip = new HTMLStripCharacterFilter();

$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$index = new SqlIndex($db, $french, 'passage_');

// 2. Index every passage; the id encodes the document and the passage number.
$passages = [];
foreach (require __DIR__.'/corpus.php' as $d => $document) {
    $text = trim((string) preg_replace('/\s+/u', ' ', $strip->filter($document['body'])));
    foreach ($chunker->chunk($text) as $chunk) {
        $id = count($passages) + 1;
        $passages[$id] = ['title' => $document['title'], 'chunk' => $chunk];
        $index->add($id, $document['title'], $chunk->text);
    }
}

// 3. Retrieve the passages most relevant to the question.
$question = $argv[1] ?? 'Quand peut-on observer les baleines et où ?';
$hits = $index->search($question, limit: 3);

// 4. Build the prompt. Each source keeps its document and offsets, to show
//    or link the exact passage next to the answer.
$sources = [];
foreach ($hits as $n => $hit) {
    $passage = $passages[$hit['id']];
    $sources[] = sprintf('[%d] %s (caractères %d-%d)%s%s', $n + 1, $passage['title'], $passage['chunk']->startOffset, $passage['chunk']->endOffset, "\n", $passage['chunk']->text);
}

$prompt = "Réponds à la question en t'appuyant uniquement sur les sources ci-dessous, "
    ."et cite-les avec leur numéro, par exemple [1]. Si elles ne suffisent pas, dis-le.\n\n"
    .'Sources :'."\n\n".implode("\n\n", $sources)."\n\n"
    .'Question : '.$question."\n";

/**
 * Plug your LLM here (HTTP API, local model...). Without one, the example
 * prints the prompt that would be sent.
 *
 * @param callable(string): string|null $llm
 */
function answer(string $prompt, ?callable $llm = null): string
{
    return null === $llm ? "(no LLM configured, prompt below)\n\n".$prompt : $llm($prompt);
}

printf("%d passages indexed from %d documents.\n\n", count($passages), count(require __DIR__.'/corpus.php'));
echo answer($prompt);
