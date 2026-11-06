<?php

declare(strict_types=1);

/*
 * Full-text search with plain SQL: an inverted index in two tables and BM25
 * ranking in one standard SQL query (examples/SqlIndex.php), highlighting with TermMatcher and
 * search-as-you-type with edge n-grams. Standard SQL through PDO: runs on
 * MySQL, MariaDB, SQLite (default, in memory) and PostgreSQL.
 *
 * Run: php examples/search-with-sql.php ["your query"]
 * On MySQL / MariaDB: see examples/database.php (EXAMPLES_DSN).
 */

use TokenizerEngine\Examples\SqlIndex;
use TokenizerEngine\Highlighting\TermMatcher;
use TokenizerEngine\TokenizerEngine;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/SqlIndex.php';

// 1. Analyzers, declared like in Elasticsearch: one for the HTML documents,
//    one for search-as-you-type (edge n-grams at index time only).
$settings = ['analysis' => [
    'filter' => [
        'french_elision' => ['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']],
        'french_stop' => ['type' => 'stop', 'stopwords' => '_french_'],
        'french_stemmer' => ['type' => 'stemmer', 'language' => 'light_french'],
        'prefixes' => ['type' => 'edge_ngram', 'min_gram' => 1, 'max_gram' => 15],
    ],
    'analyzer' => [
        'french_html' => ['tokenizer' => 'standard', 'char_filter' => ['html_strip'], 'filter' => ['french_elision', 'lowercase', 'french_stop', 'french_stemmer']],
        'autocomplete' => ['tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'asciifolding', 'prefixes']],
        'autocomplete_search' => ['tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'asciifolding']],
    ],
]];
$french = TokenizerEngine::fromElasticsearch($settings, 'french_html');
$autocomplete = TokenizerEngine::fromElasticsearch($settings, 'autocomplete');
$autocompleteSearch = TokenizerEngine::fromElasticsearch($settings, 'autocomplete_search');

// 2. Index: documents and postings (term -> document, term frequency) in
//    examples/SqlIndex.php, plus the prefixes of the titles here.
/** @var PDO $db */
$db = require __DIR__.'/database.php';
$db->exec('DROP TABLE IF EXISTS title_prefixes');
$index = new SqlIndex($db, $french);
$db->exec('CREATE TABLE title_prefixes (prefix '.SqlIndex::termType($db).' NOT NULL, document_id INTEGER NOT NULL, PRIMARY KEY (prefix, document_id))');
$insertPrefix = $db->prepare('INSERT INTO title_prefixes (prefix, document_id) VALUES (?, ?)');

foreach (require __DIR__.'/corpus.php' as $i => $document) {
    $index->add($i + 1, $document['title'], $document['body']);
    foreach (array_unique($autocomplete->tokenize($document['title'])) as $prefix) {
        $insertPrefix->execute([$prefix, $i + 1]);
    }
}

/**
 * Wraps the matching text segments in <mark>: tags of the source are kept valid.
 */
function highlight(TermMatcher $matcher, string $html, string $query): string
{
    $out = '';
    $cursor = 0;
    foreach ($matcher->match($html, $query) as $match) {
        foreach ($match->segments as $segment) {
            $out .= mb_substr($html, $cursor, $segment['start'] - $cursor).'<mark>'.$segment['text'].'</mark>';
            $cursor = $segment['end'];
        }
    }

    return $out.mb_substr($html, $cursor);
}

/**
 * Titles whose words start with every word typed so far.
 *
 * @return list<string>
 */
function suggest(PDO $db, TokenizerEngine $analyzer, string $typed): array
{
    $prefixes = array_values(array_unique($analyzer->tokenize($typed)));
    if ([] === $prefixes) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($prefixes), '?'));
    $statement = $db->prepare("SELECT d.title FROM title_prefixes t JOIN documents d ON d.id = t.document_id
        WHERE t.prefix IN ($placeholders) GROUP BY d.id, d.title HAVING COUNT(*) = ".count($prefixes).' ORDER BY d.title');
    $statement->execute($prefixes);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

// 4. Searching.
$query = $argv[1] ?? 'écoles de français à Montréal';
$matcher = new TermMatcher($french);

echo "Search: $query\n\n";
foreach ($index->search($query) as $rank => $hit) {
    printf("%d. %s (score %.2f)\n   %s\n\n", $rank + 1, $hit['title'], $hit['score'], highlight($matcher, $hit['body'], $query));
}

foreach (['mon', 'vieux qu', 'sir'] as $typed) {
    printf("Suggestions for \"%s\": %s\n", $typed, implode(' | ', suggest($db, $autocompleteSearch, $typed)));
}
