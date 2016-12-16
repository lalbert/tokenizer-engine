<?php

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use TokenizerEngine\TokenFilter\AsciiFoldingTokenFilter;

class AsciiFoldingTokenFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testFilter()
    {
        $tokens = [
            'Français' => 'Francais',
            'À' => 'A',
            'là' => 'la',
            'Écoute' => 'Ecoute',
            'Île' => 'Ile',
            'l\'œuf' => 'l\'oeuf',
            'Lætitia' => 'Laetitia',
            'Laëtitia' => 'Laetitia',
        ];
        $filter = new AsciiFoldingTokenFilter();
        $filtered = $filter->filter(array_keys($tokens));

        $this->assertEquals(array_values($tokens), $filtered);
    }
}
