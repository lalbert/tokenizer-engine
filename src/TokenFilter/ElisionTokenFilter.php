<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;

/**
 * Removes elisions at the beginning of terms ("l'avion" becomes "avion"),
 * like Elasticsearch's `elision` filter. Defaults to French articles.
 * Handles both ' and ’ (U+2019).
 */
final class ElisionTokenFilter extends AbstractTermTokenFilter
{
    public const FRENCH_ARTICLES = ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu'];
    public const ITALIAN_ARTICLES = ['c', 'l', 'all', 'dall', 'dell', 'nell', 'sull', 'coll', 'pell', 'gl', 'agl', 'dagl', 'degl', 'negl', 'sugl', 'un', 'm', 't', 's', 'v', 'd'];
    public const CATALAN_ARTICLES = ['d', 'l', 'm', 'n', 's', 't'];
    public const IRISH_ARTICLES = ['d', 'm', 'b'];

    private readonly ?string $regex;

    /**
     * @param list<string>|null $articles     null means French articles
     * @param bool              $articlesCase true = case insensitive (Elasticsearch `articles_case`)
     */
    public function __construct(?array $articles = null, bool $articlesCase = false)
    {
        $articles ??= self::FRENCH_ARTICLES;
        $articles = array_filter($articles, static fn (string $a): bool => '' !== $a);

        // Longest first so that "jusqu" wins over "j".
        usort($articles, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $this->regex = [] === $articles
            ? null
            : '/^(?:'.implode('|', array_map(static fn (string $a): string => preg_quote($a, '/'), $articles)).")['\u{2019}]/u".($articlesCase ? 'i' : '');
    }

    protected function filterTerm(string $term, Token $token): string
    {
        return null === $this->regex ? $term : (string) preg_replace($this->regex, '', $term, 1);
    }
}
