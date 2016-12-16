<?php

namespace TokenizerEngine\CharacterFilter;

class HTMLStripCharacterFilter implements CharacterFilterInterface
{
    protected $allowableTags = '';

    public function __construct($allowableTags = '')
    {
        $this->allowableTags = $allowableTags;
    }

    public function filter($string)
    {
        return strip_tags($string, $this->allowableTags);
    }
}
