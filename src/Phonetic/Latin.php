<?php

declare(strict_types=1);

namespace TokenizerEngine\Phonetic;

use TokenizerEngine\Util\AsciiFoldingMap;

/**
 * @internal
 */
final class Latin
{
    /**
     * Uppercase A-Z only: accents folded, everything else removed.
     *
     * @param array<string, string> $before replacements applied before folding (e.g. ['ß' => 'SS'])
     */
    public static function letters(string $word, array $before = []): string
    {
        $word = mb_strtoupper($word, 'UTF-8');
        if ([] !== $before) {
            $word = strtr($word, $before);
        }
        $word = strtoupper(strtr($word, AsciiFoldingMap::get()));

        return (string) preg_replace('/[^A-Z]/', '', $word);
    }
}
