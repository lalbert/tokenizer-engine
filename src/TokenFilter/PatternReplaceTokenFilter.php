<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Token;
use TokenizerEngine\Util\Text;

/**
 * Replaces regular expression matches in each term, like Elasticsearch's
 * `pattern_replace` filter. With $all = false only the first match is
 * replaced.
 */
final class PatternReplaceTokenFilter extends AbstractTermTokenFilter
{
    private readonly string $regex;

    public function __construct(string $pattern, private readonly string $replacement = '', private readonly bool $all = true, string $flags = '')
    {
        $this->regex = Text::pattern($pattern, $flags);
    }

    protected function filterTerm(string $term, Token $token): string
    {
        return (string) preg_replace($this->regex, $this->replacement, $term, $this->all ? -1 : 1);
    }
}
