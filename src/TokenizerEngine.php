<?php

namespace TokenizerEngine;

use TokenizerEngine\CharacterFilter\CharacterFilterInterface;
use TokenizerEngine\Tokenizer\TokenizerInterface;
use TokenizerEngine\TokenFilter\TokenFilterInterface;

class TokenizerEngine
{
    /**
     * @var CharacterFilterInterface[]
     */
    protected $characterFilters = [];

    /**
     * @var TokenizerInterface[]
     */
    protected $tokenizers = [];

    /**
     * @var TokenFilterInterface[]
     */
    protected $tokenFilters = [];

    public function __construct(array $config = [])
    {
        if (isset($config['characterFilters'])) {
            foreach ((array) $config['characterFilters'] as $filter) {
                $this->addCharacterFilter($filter);
            }
        }

        if (isset($config['tokenizers'])) {
            foreach ((array) $config['tokenizers'] as $tokenizer) {
                $this->addTokenizer($tokenizer);
            }
        }

        if (isset($config['tokenFilters'])) {
            foreach ((array) $config['tokenFilters'] as $filter) {
                $this->addTokenFilter($filter);
            }
        }
    }

    /**
     * @param CharacterFilterInterface $characterFilter
     *
     * @return \Engine\Engine
     */
    public function addCharacterFilter(CharacterFilterInterface $characterFilter)
    {
        $this->characterFilters[] = $characterFilter;

        return $this;
    }

    /**
     * @param TokenizerInterface $tokenizer
     *
     * @return \Engine\Engine
     */
    public function addTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizers[] = $tokenizer;

        return $this;
    }

    /**
     * @param TokenFilterInterface $filter
     *
     * @return \Engine\Engine
     */
    public function addTokenFilter(TokenFilterInterface $filter)
    {
        $this->tokenFilters[] = $filter;

        return $this;
    }

    /**
     * Tokenize $string.
     *
     * @param string $string
     *
     * @return array
     */
    public function tokenize($string)
    {
        $result = $string;

        foreach ($this->characterFilters as $filter) {
            $result = $filter->filter($result);
        }

        foreach ($this->tokenizers as $tokenizer) {
            $result = $tokenizer->tokenize($result);
        }

        foreach ($this->tokenFilters as $filter) {
            $result = $filter->filter($result);
        }

        return $result;
    }

    /**
     * Useful to debug.
     *
     * @param string $string
     *
     * @return array
     */
    public function analyze($string)
    {
        $result = [];
        $result['characterFilters'] = [];
        $result['tokenizers'] = [];
        $result['tokenFilters'] = [];
        $result['final'] = $string;

        foreach ($this->characterFilters as $filter) {
            $class = get_class($filter);

            if (!array_key_exists($class, $result['characterFilters'])) {
                $result['characterFilters'][$class] = [];
            }

            $output = $filter->filter($result['final']);

            $result['characterFilters'][$class][] = [
                'input' => $result['final'],
                'output' => $output,
            ];

            $result['final'] = $output;
        }

        foreach ($this->tokenizers as $tokenizer) {
            $class = get_class($tokenizer);

            if (!array_key_exists($class, $result['tokenizers'])) {
                $result['tokenizers'][$class] = [];
            }

            $output = $tokenizer->tokenize($result['final']);

            $result['tokenizers'][$class][] = [
                'input' => $result['final'],
                'output' => $output,
            ];

            $result['final'] = $output;
        }

        foreach ($this->tokenFilters as $filter) {
            $class = get_class($filter);

            if (!array_key_exists($class, $result['tokenFilters'])) {
                $result['tokenFilters'][$class] = [];
            }

            $output = $filter->filter($result['final']);

            $result['tokenFilters'][$class][] = [
                'input' => $result['final'],
                'output' => $output,
            ];

            $result['final'] = $output;
        }

        return $result;
    }
}
