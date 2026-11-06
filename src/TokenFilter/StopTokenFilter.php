<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Util\WordList;

/**
 * Removes stop words, like Elasticsearch's `stop` filter.
 *
 * $stopWords is either a list of words or a predefined list name:
 * _english_ (default, same list as Elasticsearch/Lucene), _french_,
 * _german_, _spanish_, _italian_, _portuguese_, _dutch_ (Snowball lists)
 * or _none_.
 */
final class StopTokenFilter extends AbstractTokenFilter
{
    /**
     * @var array<string, true>
     */
    private readonly array $stopWords;

    /**
     * @param string|list<string>|null $stopWords
     */
    public function __construct(
        string|array|null $stopWords = '_english_',
        ?string $stopWordsPath = null,
        private readonly bool $ignoreCase = false,
        private readonly bool $removeTrailing = true,
    ) {
        if (\is_string($stopWords)) {
            $stopWords = self::predefined($stopWords);
        }

        if (null !== $stopWordsPath) {
            $stopWords = WordList::words($stopWordsPath);
        }

        $set = [];
        foreach ($stopWords ?? [] as $word) {
            $set[$this->normalize($word)] = true;
        }
        $this->stopWords = $set;
    }

    /**
     * Names accepted by the constructor, e.g. "_french_".
     *
     * @return list<string>
     */
    public static function availableLists(): array
    {
        $names = ['_none_'];
        foreach (glob(self::resourcesDir().'/*.txt') ?: [] as $file) {
            $name = basename($file, '.txt');
            if (preg_match('/^[a-z]+$/', $name)) {
                $names[] = '_'.$name.'_';
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function predefined(string $name): array
    {
        if (!preg_match('/^_([a-z]+)_$/', $name, $match)) {
            throw new \InvalidArgumentException(\sprintf('Invalid stop words list name "%s", expected something like "_english_".', $name));
        }

        if ('none' === $match[1]) {
            return [];
        }

        $path = self::resourcesDir().'/'.$match[1].'.txt';
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Unknown stop words list "%s". Available: %s.', $name, implode(', ', self::availableLists())));
        }

        return WordList::words($path);
    }

    /**
     * @return list<string>
     */
    public function getStopWords(): array
    {
        return array_map('strval', array_keys($this->stopWords));
    }

    protected function process(array $tokens): array
    {
        $result = [];
        $last = \count($tokens) - 1;

        foreach ($tokens as $i => $token) {
            if (isset($this->stopWords[$this->normalize($token->term)]) && ($this->removeTrailing || $i !== $last)) {
                continue;
            }
            $result[] = $token;
        }

        return $result;
    }

    private function normalize(string $word): string
    {
        return $this->ignoreCase ? mb_strtolower($word, 'UTF-8') : $word;
    }

    private static function resourcesDir(): string
    {
        return \dirname(__DIR__, 2).'/resources/stop-words';
    }
}
