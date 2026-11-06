<?php

declare(strict_types=1);

/*
 * Database connection of the examples: SQLite in memory by default, or the
 * database given by EXAMPLES_DSN, for instance MySQL / MariaDB:
 *
 *   EXAMPLES_DSN='mysql:host=127.0.0.1;dbname=examples;charset=utf8mb4' \
 *   EXAMPLES_DB_USER=examples EXAMPLES_DB_PASSWORD=secret php examples/search-with-sql.php
 *
 * The examples create (and first drop) their own tables.
 */

return new PDO(
    getenv('EXAMPLES_DSN') ?: 'sqlite::memory:',
    getenv('EXAMPLES_DB_USER') ?: null,
    getenv('EXAMPLES_DB_PASSWORD') ?: null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
