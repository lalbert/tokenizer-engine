<?php

namespace TokenizerEngine\TokenFilter;

class StopWordsTokenFilter implements TokenFilterInterface
{
    protected $stopWords = [];

    public function __construct(array $stopWords = [])
    {
        $this->stopWords = $stopWords;
    }

    public function filter(array $tokens)
    {
        return array_map([$this, 'stopWords'], (array) $tokens);
    }

    private function stopWords($string)
    {
        foreach ($this->stopWords as $stopWord) {
            $string = preg_replace('/\b'.$stopWord.'\b/i', '', $string);
        }

        return $string;
    }
}
