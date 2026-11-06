<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

use TokenizerEngine\Util\OffsetRewriter;

/**
 * Strips HTML, like Elasticsearch's `html_strip` character filter: removes
 * inline tags and comments, replaces other tags and <script>/<style>
 * elements with a line break so that words do not get glued together, and
 * decodes HTML entities. Offsets can be mapped back to the HTML source.
 */
final class HTMLStripCharacterFilter implements OffsetMappingCharacterFilterInterface
{
    /**
     * Inline elements are removed; every other tag (block, unknown, <script>,
     * <style>) becomes a line break, like Lucene's HTMLStripCharFilter.
     */
    private const INLINE_TAGS = ['a', 'abbr', 'acronym', 'b', 'basefont', 'bdo', 'big', 'cite', 'code', 'dfn', 'em', 'font', 'i', 'img', 'input', 'kbd', 'label', 'q', 's', 'samp', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'textarea', 'tt', 'u', 'var'];

    /**
     * @var list<string>
     */
    private readonly array $escapedTags;

    /**
     * @param string|list<string> $escapedTags Tags to keep, either as a list
     *                                         (['b', 'i']) or in strip_tags()
     *                                         format ('<b><i>')
     */
    public function __construct(string|array $escapedTags = [])
    {
        if (\is_string($escapedTags)) {
            preg_match_all('/<\s*([a-z0-9]+)\s*>/i', $escapedTags, $matches);
            $escapedTags = $matches[1];
        }

        $this->escapedTags = array_values(array_map(
            static fn (string $tag): string => strtolower(trim($tag, " <>/\t\n")),
            $escapedTags,
        ));
    }

    public function filter(string $text): string
    {
        return $this->filterWithOffsets($text)[0];
    }

    public function filterWithOffsets(string $text): array
    {
        $inlineTags = array_flip(self::INLINE_TAGS);
        $escaped = array_flip($this->escapedTags);

        return OffsetRewriter::rewrite(
            $text,
            '~(?<comment><!--.*?-->)|(?<script><(script|style)\b[^>]*>.*?</\3\s*>)|(?<tag></?(?<name>[a-zA-Z][a-zA-Z0-9]*)\b[^>]*>)|(?<entity>&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);)~su',
            static function (array $m) use ($inlineTags, $escaped): ?array {
                if ('' !== ($m['entity'] ?? '')) {
                    $decoded = html_entity_decode($m['entity'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                    $decoded = "\u{00A0}" === $decoded ? ' ' : $decoded;

                    return $decoded === $m['entity'] ? null : [$decoded, false];
                }

                if ('' !== ($m['tag'] ?? '')) {
                    $name = strtolower($m['name']);
                    if (isset($escaped[$name])) {
                        return null;
                    }

                    // Other elements become a line break so that words do not get glued together.
                    return [isset($inlineTags[$name]) ? '' : "\n", true];
                }

                if ('' !== ($m['script'] ?? '')) {
                    return ["\n", true];
                }

                return ['', true]; // comments
            },
        );
    }
}
