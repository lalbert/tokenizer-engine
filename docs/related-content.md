# Related content with plain SQL

Suggest the documents closest to the one being read ("related articles",
"similar products"), or to a text being written (a question or a support
ticket, to find the answers that already exist). Runnable example:
[`examples/related-content.php`](../examples/related-content.php).

```bash
php examples/related-content.php "Nous voulons faire une sortie en bateau sur le fleuve à Québec."
```

It uses the index of [Full-text search with plain SQL](search-with-sql.md)
(SQLite in memory by default, MySQL, MariaDB or PostgreSQL with `EXAMPLES_DSN`).

## 1. Keep the terms that characterize the text

This is the principle of Elasticsearch's `more_like_this` query. Analyze the
text with the analyzer of the index, then weight each term by its frequency in
the text times its rarity in the index (`tf * idf`), and keep the best ones
(25 by default):

```php
$index->related($id);            // a stored document: its terms come from the postings table
$index->relatedToText($draft);   // any text: it is analyzed first
```

Common words weigh little ("montreal" appears in many documents), specific
ones a lot ("traversier", "baleine"). For a stored document, terms that appear
in no other document are skipped: they cannot bring anything.

## 2. Rank with BM25

The selected terms are searched like a query, without requiring all of them,
with the same BM25 query as the search (the source document is excluded):

```sql
... JOIN documents d ON d.id = p.document_id
WHERE d.id NOT IN (1)
GROUP BY d.id, d.title, d.body ORDER BY score DESC LIMIT 6;
```

## 3. Drop near-duplicates

A copy of the document (republished, translated back, slightly edited) is
always the "most related", and the least useful. MinHash signatures of word
pairs estimate how much two texts overlap:

```php
$signature = TokenizerEngine::fromElasticsearch(['analysis' => [
    'filter' => ['pairs' => ['type' => 'shingle', 'output_unigrams' => false], 'signature' => ['type' => 'min_hash', 'hash_count' => 128]],
    'analyzer' => ['signature' => ['tokenizer' => 'standard', 'char_filter' => ['html_strip'], 'filter' => ['lowercase', 'asciifolding', 'pairs', 'signature']]],
]], 'signature');

MinHashTokenFilter::similarity($signature->tokenize($a), $signature->tokenize($b)); // 0 to 1
```

Ask for more candidates than you show, then skip those above a threshold (0.5
in the example).

## 4. In production

- Compute the suggestions of each document when it is indexed or updated
  (and from time to time for all, as the index changes), and store them in a
  table: displaying them is then a simple read.
- Store the MinHash signatures in a column rather than recomputing them.
- Mix with other signals if they matter: same category, recent first,
  popularity.

## Limits

- Lexical: documents share words, not ideas. "voiture" does not bring
  "automobile" without a synonym filter; for meaning, combine with embeddings
  (hybrid ranking).
- Very short texts (a title, a few tags) give little signal.
- Content only: it knows nothing of the users, unlike collaborative filtering
  ("readers of A also read B"), which is another tool.
