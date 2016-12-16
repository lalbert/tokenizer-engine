<?php

namespace TokenizerEngine\CharacterFilter;

interface CharacterFilterInterface
{
    /**
     * @param string $string
     *
     * @return string
     */
    public function filter($string);
}
