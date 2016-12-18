<?php

namespace TokenizerEngine\TokenFilter;

class StopTokenFilter implements TokenFilterInterface
{
    /**
     * @var array
     */
    private $stopWords = [];

    /**
     * @var bool
     */
    private $ignoreCase = false;

    /**
     * @var bool
     */
    private $removeTrailing = true;

    /**
     * @param string|array $stopWords
     * @param string       $stopWordsPath
     * @param bool         $ignoreCase
     * @param bool         $removeTrailing
     */
    public function __construct($stopWords = '_english_', $stopWordsPath = null, $ignoreCase = false, $removeTrailing = true)
    {
        $language = 'english';
        if (is_string($stopWords) && preg_match('/^_(\w+)_$/', $stopWords, $language)) {
            $stopWords = $this->loadIncludeStopWords($language[1]);
        }

        if (!is_null($stopWordsPath)) {
            $stopWords = $this->loadFromPath($stopWordsPath);
        }

        if (is_array($stopWords)) {
            $this->stopWords = $stopWords;
        }

        $this->ignoreCase = (bool) $ignoreCase;
        $this->removeTrailing = (bool) $removeTrailing;
    }

    public function getStopWords()
    {
        return $this->stopWords;
    }

    public function filter(array $tokens)
    {
        return array_filter((array) $tokens, [$this, 'isNotStopWord']);
    }

    public function loadIncludeStopWords($language)
    {
        if ('none' == $language) {
            return [];
        }

        $filename = __DIR__."/../../ressources/stop-words/$language.txt";

        return $this->loadFromPath($filename);
    }

    public function loadFromPath($path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('Filename "%s" does not exists.', $path));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Filename "%s" is not readable.', $path));
        }

        return array_filter(array_map('trim', preg_split('/\s+/i', file_get_contents($path))));
    }

    private function isStopWord($string)
    {
        $option = $this->ignoreCase ? 'i' : '';
        foreach ($this->stopWords as $stopWord) {
            if (preg_match('/\b'.preg_quote($stopWord, '/').'\b/'.$option, $string)) {
                return true;
            }
        }

        return false;
    }

    private function isNotStopWord($string)
    {
        return !$this->isStopWord($string);
    }
}
