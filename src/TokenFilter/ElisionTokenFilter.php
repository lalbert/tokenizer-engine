<?php

namespace TokenizerEngine\TokenFilter;

class ElisionTokenFilter implements TokenFilterInterface
{
    protected $articles = [];

    public function __construct(array $articles = [])
    {
        $this->articles = $articles;
    }

    public function filter(array $tokens)
    {
        foreach ($this->articles as $article) {
            $tokens = str_replace("{$article}'", '', (array) $tokens);
        }

        return $tokens;
    }
}
