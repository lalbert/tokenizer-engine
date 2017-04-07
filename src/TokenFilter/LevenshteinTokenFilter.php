<?php

namespace TokenizerEngine\TokenFilter;

class LevenshteinTokenFilter implements TokenFilterInterface
{
    private $words = [];
    private $maxDistance;

    public function __construct(array $words = [], $maxDistance = PHP_INT_MAX)
    {
        $this->words = $words;
        $this->maxDistance = $maxDistance;
    }

    public function filter(array $tokens)
    {
        return array_map([$this, 'levenshteinSearch'], (array) $tokens);
    }

    private function levenshteinSearch($token)
    {
        $shortest = -1;

        foreach ($this->words as $word) {
            // calcule la distance avec le mot mis en entrée,
            // et le mot courant
            $lev = levenshtein($token, $word);

            if ($lev > $this->maxDistance) {
                continue;
            }

            // cherche une correspondance exacte
            if ($lev == 0) {
                return $token;
            }

            // Si la distance est plus petite que la prochaine distance trouvée
            // OU, si le prochain mot le plus près n'a pas encore été trouvé
            if ($lev <= $shortest || $shortest < 0) {
                // définition du mot le plus près ainsi que la distance
                $closest = $word;
                $shortest = $lev;
            }
        }

        if (!isset($closest)) {
            return $token;
        }

        return $closest;
    }
}
