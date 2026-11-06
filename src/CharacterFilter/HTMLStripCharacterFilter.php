<?php

declare(strict_types=1);

namespace TokenizerEngine\CharacterFilter;

/**
 * Strips HTML, like Elasticsearch's `html_strip` character filter:
 * removes tags (and the content of <script>, <style>, comments), replaces
 * block level elements with a line break so words do not get glued together,
 * and decodes HTML entities.
 */
final class HTMLStripCharacterFilter implements CharacterFilterInterface
{
    private const BLOCK_TAGS = 'address|article|aside|blockquote|br|dd|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul';

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
        $text = (string) preg_replace('~<!--.*?-->~su', '', $text);
        $text = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~isu', '', $text);

        $blockTags = array_diff(explode('|', self::BLOCK_TAGS), $this->escapedTags);
        if ([] !== $blockTags) {
            $text = (string) preg_replace('~</?(?:'.implode('|', $blockTags).')\b[^>]*>~iu', "\n", $text);
        }

        $text = strip_tags($text, $this->escapedTags);

        return html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
