# Full-text search with plain SQL

This library analyzes text; it is not a search engine. But with an SQL
database you already have, an analyzer and two tables are enough for a
correct full-text search on small and medium datasets (up to some hundreds of
thousands of documents). Runnable example: [`examples/search-with-sql.php`](../examples/search-with-sql.php).

```bash
php examples/search-with-sql.php "écoles de français à Montréal"
```

## 1. Analyze like Elasticsearch

Declare the analyzers as you would in Elasticsearch; the same settings can
configure a cluster later:

```php
$french = TokenizerEngine::fromElasticsearch($settings, 'french_html'); // html_strip, elision, lowercase, stop, light_french
```

Index and query must use the same analyzer: "Écoles" and "ecole" both become
`ecol`.

## 2. An inverted index in two tables

```sql
CREATE TABLE documents (id INTEGER PRIMARY KEY, title TEXT NOT NULL, body TEXT NOT NULL, length INTEGER NOT NULL);
CREATE TABLE postings (term VARCHAR(100) NOT NULL, document_id INTEGER NOT NULL, tf INTEGER NOT NULL, PRIMARY KEY (term, document_id));
```

For each document: analyze it, store its number of terms (`length`), and one
row per distinct term with its frequency (`tf`):

```php
$terms = $french->tokenize($title.' '.$body);
foreach (array_count_values($terms) as $term => $tf) {
    $insert->execute([$term, $id, $tf]); // INSERT INTO postings ...
}
```

## 3. Rank with BM25 in one query

BM25 is the default ranking of Elasticsearch. For a query, analyze it, compute
the IDF of each term in PHP (`log(1 + (N - n + 0.5) / (n + 0.5))`, where `n` is
the number of documents containing the term), then:

```sql
SELECT d.id, d.title,
       SUM(q.idf * (p.tf * 2.2) / (p.tf + 1.2 * (1 - 0.75 + 0.75 * d.length / :avg_length))) AS score
FROM (SELECT 'ecol' AS term, 1.28 AS idf
      UNION ALL SELECT 'montreal', 0.94) q
JOIN postings p ON p.term = q.term
JOIN documents d ON d.id = p.document_id
GROUP BY d.id, d.title
ORDER BY score DESC
LIMIT 10;
```

(`k1 = 1.2`, `b = 0.75`, the defaults of Elasticsearch.) The query is
standard SQL: it runs on SQLite, MySQL and PostgreSQL. The example builds it
with placeholders in [`examples/SqlIndex.php`](../examples/SqlIndex.php).

## 4. Highlight the results

`TermMatcher` gives the passages to highlight, with offsets in the HTML source
and segments that skip the tags:

```php
foreach ((new TermMatcher($french))->match($html, $query) as $match) {
    foreach ($match->segments as $segment) { /* wrap $segment['text'] in <mark> */ }
}
```

## 5. Search-as-you-type

Index the prefixes of the titles with an `edge_ngram` analyzer, and analyze
what is typed without it:

```sql
CREATE TABLE title_prefixes (prefix VARCHAR(100) NOT NULL, document_id INTEGER NOT NULL, PRIMARY KEY (prefix, document_id));

-- "vieux qu": titles having both prefixes
SELECT d.title FROM title_prefixes t JOIN documents d ON d.id = t.document_id
WHERE t.prefix IN ('vieux', 'qu') GROUP BY d.id, d.title HAVING COUNT(*) = 2;
```

## Going further

- Store `positions` in the postings to rank phrases higher.
- Several fields: one postings table per field, or a `field` column, and a
  weight per field in the score.
- Beyond a few million postings, a search engine (Elasticsearch, OpenSearch,
  Meilisearch, PostgreSQL `tsvector`...) becomes the right tool: the analyzers
  declared here can be reused as they are in Elasticsearch settings.
