<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

/**
 * Loads word lists (stop words, synonyms, protected words...) from files.
 *
 * Supported comment markers: "#" (Lucene/Solr) and "|" (Snowball).
 *
 * @internal
 */
final class WordList
{
    /**
     * @return list<string> non empty, trimmed lines without comments
     */
    public static function lines(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Filename "%s" does not exist.', $path));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(\sprintf('Filename "%s" is not readable.', $path));
        }

        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $lines = [];
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s[|#].*$|^[|#].*$/u', '', $line));
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * One or more words per line (whitespace separated).
     *
     * @return list<string>
     */
    public static function words(string $path): array
    {
        $words = [];
        foreach (self::lines($path) as $line) {
            foreach (preg_split('/\s+/u', $line, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $words[] = $word;
            }
        }

        return $words;
    }
}
