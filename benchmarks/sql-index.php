<?php

declare(strict_types=1);

/*
 * Scale of the SQL index of the examples (examples/SqlIndex.php): indexing
 * time and query latency for N synthetic documents of about 2 KB (300 words
 * drawn from a 50,000-word vocabulary with a Zipf distribution, a quarter of
 * French stop words), with the French analyzer.
 *
 * Run: php -d memory_limit=2G benchmarks/sql-index.php 20000
 * The database is SQLite in memory by default, or EXAMPLES_DSN (see
 * examples/database.php); for a SQLite file: EXAMPLES_DSN=sqlite:/tmp/index.db
 */

use TokenizerEngine\Analyzers;
use TokenizerEngine\Examples\SqlIndex;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../examples/SqlIndex.php';

$count = (int) ($argv[1] ?? 10000);
mt_srand(42);

$syllables = ['ba', 'ce', 'di', 'fo', 'gu', 'la', 'me', 'ni', 'po', 'ru', 'sa', 'te', 'vi', 'zo', 'an', 'en', 'on', 'ou', 'ré', 'té', 'mé', 'lé', 'char', 'tran', 'ple', 'gra', 'vol', 'mur', 'sel', 'pir'];
$vocabulary = [];
for ($i = 0; $i < 50000; ++$i) {
    $word = '';
    for ($j = 2 + mt_rand(0, 2); $j > 0; --$j) {
        $word .= $syllables[mt_rand(0, count($syllables) - 1)];
    }
    $vocabulary[] = $word;
}

// Zipf distribution: the word of rank r is drawn with a probability in 1 / r.
$cumulative = [];
$sum = 0.0;
foreach (array_keys($vocabulary) as $rank) {
    $sum += 1 / ($rank + 1);
    $cumulative[] = $sum;
}
$draw = static function () use ($cumulative, $sum, $vocabulary): string {
    $x = mt_rand() / mt_getrandmax() * $sum;
    [$low, $high] = [0, count($cumulative) - 1];
    while ($low < $high) {
        $middle = intdiv($low + $high, 2);
        $cumulative[$middle] < $x ? $low = $middle + 1 : $high = $middle;
    }

    return $vocabulary[$low];
};

$stopWords = ['le', 'la', 'les', 'de', 'des', 'et', 'à', 'un', 'une', 'du', 'en', 'pour', 'dans', 'sur'];
$documents = [];
for ($d = 0; $d < $count; ++$d) {
    $words = [];
    for ($i = 0; $i < 300; ++$i) {
        $words[] = 0 === mt_rand(0, 3) ? $stopWords[mt_rand(0, count($stopWords) - 1)] : $draw();
    }
    $documents[] = implode(' ', $words).'.';
}

/** @var PDO $db */
$db = require __DIR__.'/../examples/database.php';
$index = new SqlIndex($db, Analyzers::french(), 'bench_');

$start = microtime(true);
$db->beginTransaction();
foreach ($documents as $i => $document) {
    $index->add($i + 1, 'Document '.($i + 1), $document);
    if (999 === $i % 1000) {
        $db->commit();
        $db->beginTransaction();
    }
}
$db->commit();
$indexing = microtime(true) - $start;

// Queries of three words: two of rank 100 to 5,000, one frequent (10 to 500).
$times = [];
for ($q = 0; $q < 20; ++$q) {
    $query = implode(' ', [$vocabulary[mt_rand(100, 5000)], $vocabulary[mt_rand(100, 5000)], $vocabulary[mt_rand(10, 500)]]);
    $start = microtime(true);
    $index->search($query, 10);
    $times[] = microtime(true) - $start;
}
sort($times);

printf(
    "%s, %d documents (%.0f MB), %d postings: indexing %.0f s (%.0f documents/s), query median %.0f ms, p95 %.0f ms\n",
    $db->getAttribute(PDO::ATTR_DRIVER_NAME),
    $count,
    array_sum(array_map('strlen', $documents)) / 1048576,
    (int) $db->query('SELECT COUNT(*) FROM bench_postings')->fetchColumn(),
    $indexing,
    $count / $indexing,
    1000 * $times[9],
    1000 * $times[18],
);
