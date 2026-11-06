<?php

declare(strict_types=1);

namespace TokenizerEngine\Examples;

use TokenizerEngine\TokenizerEngine;

/**
 * A minimal full-text index in plain SQL (example code, not part of the
 * library): an inverted index in a "postings" table and BM25 ranking in one
 * standard SQL query. Runs on MySQL, MariaDB, SQLite and PostgreSQL.
 */
final class SqlIndex
{
    public function __construct(
        private readonly \PDO $db,
        private readonly TokenizerEngine $analyzer,
        private readonly string $prefix = '',
    ) {
        $term = self::termType($db);
        $this->db->exec("DROP TABLE IF EXISTS {$this->prefix}postings");
        $this->db->exec("DROP TABLE IF EXISTS {$this->prefix}documents");
        $this->db->exec("CREATE TABLE {$this->prefix}documents (id INTEGER PRIMARY KEY, title TEXT NOT NULL, body TEXT NOT NULL, length INTEGER NOT NULL)");
        $this->db->exec("CREATE TABLE {$this->prefix}postings (term $term NOT NULL, document_id INTEGER NOT NULL, tf INTEGER NOT NULL, PRIMARY KEY (term, document_id))");
    }

    /**
     * Column type of the terms. The analyzer already decides which words are
     * equal: the database must compare terms byte for byte. The default
     * collations of MySQL and MariaDB ignore case and accents, so "pâte" and
     * "pate" would be the same key there: use a binary collation.
     */
    public static function termType(\PDO $db): string
    {
        return 'mysql' === $db->getAttribute(\PDO::ATTR_DRIVER_NAME)
            ? 'VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'
            : 'VARCHAR(100)';
    }

    /**
     * Analyzes the document and stores its terms with their frequency.
     */
    public function add(int $id, string $title, string $body): void
    {
        $terms = $this->analyzer->tokenize($title.' '.$body);

        $this->db->prepare("INSERT INTO {$this->prefix}documents (id, title, body, length) VALUES (?, ?, ?, ?)")
            ->execute([$id, $title, $body, \count($terms)]);

        $insert = $this->db->prepare("INSERT INTO {$this->prefix}postings (term, document_id, tf) VALUES (?, ?, ?)");
        foreach (array_count_values($terms) as $term => $tf) {
            $insert->execute([(string) $term, $id, $tf]);
        }
    }

    /**
     * BM25 ranking. IDF values are computed in PHP (SQL engines do not all
     * have the same logarithm function) and passed as a derived table.
     *
     * @return list<array{id: int, title: string, body: string, score: float}>
     */
    public function search(string $query, int $limit = 3, float $k1 = 1.2, float $b = 0.75): array
    {
        $terms = array_values(array_unique($this->analyzer->tokenize($query)));
        if ([] === $terms) {
            return [];
        }

        $count = (int) $this->db->query("SELECT COUNT(*) FROM {$this->prefix}documents")->fetchColumn();
        $averageLength = (float) $this->db->query("SELECT AVG(length) FROM {$this->prefix}documents")->fetchColumn();

        // Document frequency of each query term.
        $placeholders = implode(', ', array_fill(0, \count($terms), '?'));
        $statement = $this->db->prepare("SELECT term, COUNT(*) FROM {$this->prefix}postings WHERE term IN ($placeholders) GROUP BY term");
        $statement->execute($terms);
        $frequencies = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Query terms and their IDF: "SELECT ? AS term, 1.28 AS idf UNION ALL SELECT ?, 0.94 ...".
        // Terms are parameters; numbers computed here are written as SQL
        // literals, since parameters are typed as text by some drivers
        // (PostgreSQL then refuses arithmetic on them).
        $rows = [];
        foreach ($terms as $term) {
            $n = (int) ($frequencies[$term] ?? 0);
            $idf = log(1 + ($count - $n + 0.5) / ($n + 0.5));
            $rows[] = \sprintf([] === $rows ? 'SELECT ? AS term, %F AS idf' : 'SELECT ?, %F', $idf);
        }

        $statement = $this->db->prepare(\sprintf(
            'SELECT d.id, d.title, d.body,
                SUM(q.idf * (p.tf * %F) / (p.tf + %F * (1 - %F + %F * d.length / %F))) AS score
            FROM (%s) q
            JOIN %spostings p ON p.term = q.term
            JOIN %sdocuments d ON d.id = p.document_id
            GROUP BY d.id, d.title, d.body
            ORDER BY score DESC
            LIMIT %d',
            $k1 + 1, $k1, $b, $b, $averageLength, implode(' UNION ALL ', $rows), $this->prefix, $this->prefix, $limit,
        ));
        $statement->execute($terms);

        /** @var list<array{id: int, title: string, body: string, score: float}> */
        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'body' => (string) $row['body'], 'score' => (float) $row['score']],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }
}
