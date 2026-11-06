<?php

declare(strict_types=1);

/*
 * Related content ("more like this"): suggest the documents closest to the
 * one being read, or to a text being written, from their words only.
 *   1. keep the terms that characterize the text: frequent in it, rare in
 *      the index (tf * idf), like Elasticsearch's more_like_this query;
 *   2. rank the documents containing them with BM25 (examples/SqlIndex.php);
 *   3. drop near-duplicates (republished or copied texts) with MinHash.
 *
 * It finds documents that share words, not ideas: "voiture" does not bring
 * "automobile" without a synonym filter; combine with embeddings for that.
 * It knows nothing of the users either (no "readers of A also read B").
 *
 * Run: php examples/related-content.php ["a text being written"]
 * On MySQL / MariaDB: see examples/database.php (EXAMPLES_DSN).
 */

use TokenizerEngine\Examples\SqlIndex;
use TokenizerEngine\TokenFilter\MinHashTokenFilter;
use TokenizerEngine\TokenizerEngine;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/SqlIndex.php';

$french = TokenizerEngine::fromElasticsearch(['analysis' => [
    'filter' => [
        'french_elision' => ['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']],
        'french_stop' => ['type' => 'stop', 'stopwords' => '_french_'],
        'french_stemmer' => ['type' => 'stemmer', 'language' => 'light_french'],
    ],
    'analyzer' => ['french_html' => ['tokenizer' => 'standard', 'char_filter' => ['html_strip'], 'filter' => ['french_elision', 'lowercase', 'french_stop', 'french_stemmer']]],
]], 'french_html');

// MinHash signatures of word pairs, to estimate how much two texts overlap.
$signature = TokenizerEngine::fromElasticsearch(['analysis' => [
    'filter' => ['pairs' => ['type' => 'shingle', 'output_unigrams' => false], 'signature' => ['type' => 'min_hash', 'hash_count' => 128]],
    'analyzer' => ['signature' => ['tokenizer' => 'standard', 'char_filter' => ['html_strip'], 'filter' => ['lowercase', 'asciifolding', 'pairs', 'signature']]],
]], 'signature');

// The documents of the other examples, plus a copy of the first one
// republished with a few changes: it should not be suggested next to it.
$documents = require __DIR__.'/corpus.php';
$documents[] = ['title' => 'Montréal l’été', 'body' => '<p>L’été, Montréal vit dehors : festivals de jazz, terrasses sur le Plateau et pique-niques au parc du Mont-Royal. Les élèves des écoles de langues y arrivent dès juillet pour apprendre le français.</p>'];

/** @var PDO $db */
$db = require __DIR__.'/database.php';
$index = new SqlIndex($db, $french, 'related_');
$signatures = [];
foreach ($documents as $i => $document) {
    $index->add($i + 1, $document['title'], $document['body']);
    $signatures[$i + 1] = $signature->tokenize($document['body']); // store them in a column in a real application
}

/**
 * Suggestions without near-duplicates of the source (estimated Jaccard
 * similarity of word pairs of 0.5 or more).
 *
 * @param list<array{id: int, title: string, body: string, score: float}> $candidates
 * @param list<string>                                                    $source
 * @param array<int, list<string>>                                        $signatures
 *
 * @return list<array{id: int, title: string, body: string, score: float}>
 */
function withoutDuplicates(array $candidates, array $source, array $signatures, int $limit): array
{
    $kept = [];
    foreach ($candidates as $candidate) {
        $similarity = MinHashTokenFilter::similarity($source, $signatures[$candidate['id']]);
        if ($similarity >= 0.5) {
            printf("   (skipped \"%s\": near-duplicate, %.0f %% of word pairs in common)\n", $candidate['title'], 100 * $similarity);
            continue;
        }
        $kept[] = $candidate;
    }

    return array_slice($kept, 0, $limit);
}

// 1. Documents related to the one being read. Ask for more candidates than
//    shown, since near-duplicates are removed afterwards.
foreach ([1, 2, 7] as $id) {
    echo "Related to \"{$documents[$id - 1]['title']}\":\n";
    foreach (withoutDuplicates($index->related($id, 6), $signatures[$id], $signatures, 3) as $rank => $hit) {
        printf("   %d. %s (score %.2f)\n", $rank + 1, $hit['title'], $hit['score']);
    }
    echo "\n";
}

// 2. Documents related to a text being written (a question, a ticket...).
$draft = $argv[1] ?? 'Bonjour, nous voulons faire une sortie en bateau sur le fleuve pendant notre séjour à Québec. Que conseillez-vous ?';
echo "Related to the draft \"$draft\":\n";
foreach ($index->relatedToText($draft, 3) as $rank => $hit) {
    printf("   %d. %s (score %.2f)\n", $rank + 1, $hit['title'], $hit['score']);
}
