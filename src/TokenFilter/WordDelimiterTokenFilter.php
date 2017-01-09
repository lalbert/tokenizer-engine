<?php

namespace TokenizerEngine\TokenFilter;

class WordDelimiterTokenFilter implements TokenFilterInterface
{
    const CHAR_TYPE_BREAK = 'BREAK';
    const CHAR_TYPE_UPPER = 'UPPER';
    const CHAR_TYPE_LOWER = 'LOWER';
    const CHAR_TYPE_DIGIT = 'DIGIT';

    const WORD_TYPE_WORD = 'WORD';
    const WORD_TYPE_NUMBER = 'NUMBER';

    private $generateWordParts = true;
    private $generateNumberParts = true;
    private $catenateWords = false;
    private $catenateNumbers = false;
    private $catenateAll = false;
    private $splitOnCaseChange = true;
    private $preserveOriginal = false;
    private $splitOnNumerics = true;
    private $stemEnglishPossessive = true;
    private $protectedWords = [];

    public function __construct(array $config = [])
    {
        $config = array_merge([
            'generateWordParts' => true,
            'generateNumberParts' => true,
            'catenateWords' => false,
            'catenateNumbers' => false,
            'catenateAll' => false,
            'splitOnCaseChange' => true,
            'preserveOriginal' => false,
            'splitOnNumerics' => true,
            'stemEnglishPossessive' => true,
            'protectedWords' => [],
        ], $config);

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'generateWordParts':
                case 'generateNumberParts':
                case 'catenateWords':
                case 'catenateNumbers':
                case 'catenateAll':
                case 'splitOnCaseChange':
                case 'preserveOriginal':
                case 'splitOnNumerics':
                case 'stemEnglishPossessive':
                    $this->$key = (bool) $value;
                    break;

                case 'protectedWords':
                    $this->$key = (array) $value;
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('%s is not a valid parameter.', $key));
                    break;
            }
        }
    }

    public function filter(array $tokens)
    {
        $_tokens = [];

        foreach ($tokens as $token) {
            $_tokens = array_merge($_tokens, $this->preserveOriginal ? [$token] : [], $this->applyFilters($token));
        }

        return $_tokens;
    }

    private function applyFilters($token)
    {
        if ($this->stemEnglishPossessive) {
            $token = str_replace("'s", '', $token);
        }

        $parts = [];
        $subword = '';

        $previousCharType = $currentCharType = null;

        foreach (str_split($token) as $char) {
            $currentCharType = $this->charType($char);

            // Break
            if (self::CHAR_TYPE_BREAK === $currentCharType) {
                if (is_null($previousCharType)) {
                    continue;
                }

                $parts[] = $subword;
                $subword = '';
                $previousCharType = $currentCharType = null;
                continue;
            }

            // Split on case change
            if ($this->splitOnCaseChange) {
                if (self::CHAR_TYPE_UPPER === $currentCharType && !is_null($previousCharType) && self::CHAR_TYPE_UPPER !== $previousCharType) {
                    $parts[] = $subword;
                    $subword = $char;
                    $previousCharType = $currentCharType;
                    continue;
                }
            }

            // Split on numerics
            if ($this->splitOnNumerics) {
                if (self::CHAR_TYPE_DIGIT === $currentCharType && !is_null($previousCharType) && self::CHAR_TYPE_DIGIT !== $previousCharType) {
                    $parts[] = $subword;
                    $subword = $char;
                    $previousCharType = $currentCharType;
                    continue;
                }

                if (!is_null($previousCharType) && self::CHAR_TYPE_DIGIT === $previousCharType && self::CHAR_TYPE_DIGIT !== $currentCharType) {
                    $parts[] = $subword;
                    $subword = $char;
                    $previousCharType = $currentCharType;
                    continue;
                }
            }

            $subword .= $char;
            $previousCharType = $currentCharType;
        }

        if ($subword) {
            $parts[] = $subword;
        }

        $result = [];

        if ($this->catenateAll) {
            $result[] = implode('', $parts);
        }

        $previousWordType = null;
        $catenate = '';
        foreach ($parts as $part) {
            $currentWordType = $this->wordType($part);
            if (!$currentWordType) {
                continue;
            }

            if ($this->generateWordParts && $currentWordType === self::WORD_TYPE_WORD) {
                $result[] = $part;
            }

            if ($this->generateNumberParts && $currentWordType === self::WORD_TYPE_NUMBER) {
                $result[] = $part;
            }

            $catenateWordType = $this->wordType($catenate);

            if ($this->catenateWords && $currentWordType === self::WORD_TYPE_WORD) {
                if (!empty($catenate) && ($catenateWordType !== self::WORD_TYPE_WORD || $previousWordType !== self::WORD_TYPE_WORD)) {
                    $result[] = $catenate;
                    $catenate = '';
                }

                $catenate .= $part;
            }

            if ($this->catenateNumbers && $currentWordType === self::WORD_TYPE_NUMBER) {
                if (!empty($catenate) && ($catenateWordType !== self::WORD_TYPE_NUMBER || $previousWordType !== self::WORD_TYPE_NUMBER)) {
                    $result[] = $catenate;
                    $catenate = '';
                }

                $catenate .= $part;
            }

            $previousWordType = $currentWordType;
        }

        if (!empty($catenate)) {
            $result[] = $catenate;
        }

        return $result;
    }

    private function charType($char)
    {
        if (!ctype_alnum($char)) {
            return self::CHAR_TYPE_BREAK;
        }

        if (ctype_digit($char)) {
            return self::CHAR_TYPE_DIGIT;
        }

        if (ctype_lower($char)) {
            return self::CHAR_TYPE_LOWER;
        }

        if (ctype_upper($char)) {
            return self::CHAR_TYPE_UPPER;
        }

        return self::CHAR_TYPE_BREAK;
    }

    private function wordType($word)
    {
        if (ctype_alpha($word)) {
            return self::WORD_TYPE_WORD;
        }

        if (ctype_digit($word)) {
            return self::WORD_TYPE_NUMBER;
        }

        return null;
    }
}
