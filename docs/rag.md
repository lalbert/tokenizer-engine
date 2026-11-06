# Retrieval augmented generation (RAG) with plain SQL

A language model answers better, and can cite its sources, when the prompt
contains the passages of your documents that answer the question. Runnable
example: [`examples/rag.php`](../examples/rag.php).

```bash
php examples/rag.php "Quand peut-on observer les baleines et où ?"
```

## 1. Split the documents into passages

```php
$chunker = new TextChunker(150, 40, locale: 'fr');   // characters, overlap
// or: TextChunker::byTokens($analyzer, maxTokens: 300, overlapTokens: 50)
// or: new TextChunker(512, 64, fn (string $s): int => $llmTokenizer->count($s))

foreach ($chunker->chunk($text) as $chunk) {
    $chunk->text; $chunk->startOffset; $chunk->endOffset;
}
```

Passages end at sentence boundaries (then words), and overlap so that an
answer spread over two passages is not lost. Each passage keeps its offsets in
the document, to show or link the exact source.

## 2. Index and retrieve the passages

Index each passage like a document in the SQL index of
[search-with-sql.md](search-with-sql.md) (two tables, BM25 in one query),
then retrieve the best ones for the question:

```php
$index->add($passageId, $title, $chunk->text);
$hits = $index->search($question, limit: 3);
```

## 3. Build the prompt

```text
Réponds à la question en t'appuyant uniquement sur les sources ci-dessous,
et cite-les avec leur numéro, par exemple [1]. Si elles ne suffisent pas, dis-le.

Sources :
[1] Observer les baleines à Tadoussac (caractères 0-144)
À Tadoussac, là où le fjord du Saguenay rejoint le Saint-Laurent, ...

Question : Quand peut-on observer les baleines et où ?
```

Send it to any language model (HTTP API or local model): the example takes a
callable and prints the prompt when none is given.

## Keywords or embeddings?

Keyword retrieval (BM25) is precise on names, places, codes and technical
terms, needs no extra service and explains its results. Embeddings find
paraphrases ("cetaceans" for "whales"). Both can be combined (hybrid search):
retrieve candidates with each method and merge the rankings, for example with
reciprocal rank fusion (`score = Σ 1 / (60 + rank)`). The passages and their
offsets stay the same.
