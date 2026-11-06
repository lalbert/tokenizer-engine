<?php

declare(strict_types=1);

namespace TokenizerEngine\Benchmarks;

/**
 * Deterministic texts for the benchmarks.
 */
final class Corpus
{
    private const SENTENCES = [
        'Les élèves de l’École arrivent à Montréal pour l’été, avec leurs professeurs.',
        'Ils visitent la ville, ses musées et ses bibliothèques pendant trois semaines !',
        "L'organisation du voyage a demandé beaucoup de préparation et d'énergie.",
        'Les chevaux du centre équestre attendent les jeunes cavaliers chaque matin.',
        'Quelle aventure : 12 villes, 3 pays et des centaines de kilomètres parcourus.',
    ];

    /**
     * About $kilobytes KB of French prose.
     */
    public static function text(int $kilobytes): string
    {
        $text = '';
        for ($i = 0; \strlen($text) < $kilobytes * 1024; ++$i) {
            $text .= self::SENTENCES[$i % \count(self::SENTENCES)].' ';
        }

        return $text;
    }

    /**
     * About $kilobytes KB of HTML (paragraphs with inline markup and entities).
     */
    public static function html(int $kilobytes): string
    {
        $html = '<html><body>';
        for ($i = 0; \strlen($html) < $kilobytes * 1024; ++$i) {
            $sentence = htmlspecialchars(self::SENTENCES[$i % \count(self::SENTENCES)], \ENT_QUOTES | \ENT_HTML5);
            $html .= '<p class="p'.$i.'">'.preg_replace('/(École|Montréal|chevaux|voyage)/u', '<b>$1</b>', $sentence).'&nbsp;<a href="/page/'.$i.'">lien</a></p>';
        }

        return $html.'<script>var x = 1;</script></body></html>';
    }
}
