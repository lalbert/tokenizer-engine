<?php

namespace TokenizerEngine\Tokenizer;

interface TokenizerInterface
{
    /**
     * @param string|array $data
     *
     * @return array
     */
    public function tokenize($data);
}
