<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Highlighting;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Analyzers;
use TokenizerEngine\Highlighting\TermMatch;
use TokenizerEngine\Highlighting\TermMatcher;
use TokenizerEngine\TokenizerEngine;

final class TermMatcherTest extends TestCase
{
    private const HTML = '<p>Les élèves de l’<b>École</b> arrivent à Montréal.</p>';

    private static function frenchHtml(): TokenizerEngine
    {
        return TokenizerEngine::fromElasticsearch(['analysis' => ['analyzer' => ['fr_html' => [
            'tokenizer' => 'standard',
            'char_filter' => ['html_strip'],
            'filter' => [['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'd']], 'lowercase', ['type' => 'stop', 'stopwords' => '_french_'], ['type' => 'stemmer', 'language' => 'light_french']],
        ]]]], 'fr_html');
    }

    /**
     * @param list<TermMatch> $matches
     */
    private static function render(string $text, array $matches): string
    {
        $html = '';
        $cursor = 0;
        foreach ($matches as $match) {
            foreach ($match->segments as $segment) {
                $html .= mb_substr($text, $cursor, $segment['start'] - $cursor).'<mark>'.$segment['text'].'</mark>';
                $cursor = $segment['end'];
            }
        }

        return $html.mb_substr($text, $cursor);
    }

    public function testMatchesInHtmlWithSegments(): void
    {
        $matches = (new TermMatcher(self::frenchHtml()))->match(self::HTML, "l'ecol montreal");

        self::assertCount(2, $matches);
        self::assertSame(['ecol'], $matches[0]->terms);
        self::assertSame([17, 31, 'l’<b>École</b>'], [$matches[0]->start, $matches[0]->end, $matches[0]->text]);
        self::assertSame([['start' => 17, 'end' => 19, 'text' => 'l’'], ['start' => 22, 'end' => 27, 'text' => 'École']], $matches[0]->segments);
        self::assertSame([43, 51, 'Montréal'], [$matches[1]->start, $matches[1]->end, $matches[1]->text]);
    }

    public function testRenderingKeepsHtmlValid(): void
    {
        self::assertSame(
            '<p>Les élèves de <mark>l’</mark><b><mark>École</mark></b> arrivent à <mark>Montréal</mark>.</p>',
            self::render(self::HTML, (new TermMatcher(self::frenchHtml()))->match(self::HTML, 'écoles montréal')),
        );
    }

    public function testQueryIsAnalyzedLikeTheText(): void
    {
        $matcher = new TermMatcher(Analyzers::french());
        $text = 'Les élèves de l’École arrivent à Montréal.';

        foreach (["l'ecol", 'L’ÉCOLE', 'ecole', 'écoles'] as $query) {
            // Like Elasticsearch, the passage is the whole token, elided article included.
            self::assertSame(['l’École'], array_map(static fn (TermMatch $m): string => $m->text, $matcher->match($text, $query)), $query);
        }

        self::assertSame([], $matcher->match($text, 'eco'));
        self::assertSame([], $matcher->match($text, 'les'), 'stop words only');
        self::assertSame([], $matcher->match($text, ''));
    }

    public function testSearchAsYouTypeWithAQueryAnalyzer(): void
    {
        $settings = ['analysis' => [
            'filter' => [
                'prefixes' => ['type' => 'edge_ngram', 'min_gram' => 1, 'max_gram' => 15],
                'french_elision' => ['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'd', 'qu']],
            ],
            'analyzer' => [
                'autocomplete' => ['tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'asciifolding', 'prefixes']],
                'autocomplete_search' => ['tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'asciifolding']],
            ],
        ]];
        $matcher = new TermMatcher(TokenizerEngine::fromElasticsearch($settings, 'autocomplete'), TokenizerEngine::fromElasticsearch($settings, 'autocomplete_search'));

        $matches = $matcher->match('Les élèves de l’École', 'eco');

        // Edge n-grams keep the offsets of the whole word: the whole word is matched once.
        self::assertCount(1, $matches);
        self::assertSame(['eco'], $matches[0]->terms);
        self::assertSame('l’École', $matches[0]->text);
    }

    public function testOverlappingPassagesAreMerged(): void
    {
        $settings = ['analysis' => ['analyzer' => ['syn' => [
            'tokenizer' => 'standard',
            'filter' => ['lowercase', ['type' => 'synonym', 'synonyms' => ['tv, television']]],
        ]]]];
        $matches = (new TermMatcher(TokenizerEngine::fromElasticsearch($settings, 'syn')))->match('Une TV neuve', 'tv television');

        self::assertCount(1, $matches);
        self::assertSame(['tv', 'television'], $matches[0]->terms);
        self::assertSame('TV', $matches[0]->text);
    }

    public function testLongDocument(): void
    {
        $html = str_repeat(self::HTML, 200);
        $matches = (new TermMatcher(self::frenchHtml()))->match($html, 'école montréal');

        self::assertCount(400, $matches);
        foreach ($matches as $match) {
            self::assertSame(mb_substr($html, $match->start, $match->end - $match->start), $match->text);
            foreach ($match->segments as $segment) {
                self::assertSame(mb_substr($html, $segment['start'], $segment['end'] - $segment['start']), $segment['text']);
                self::assertStringNotContainsString('<', $segment['text']);
            }
        }
    }

    public function testSortedByPosition(): void
    {
        $matches = (new TermMatcher(Analyzers::standard()))->match('b a b', 'a b');

        self::assertSame([0, 2, 4], array_map(static fn (TermMatch $m): int => $m->start, $matches));
    }
}
