<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\TokenFilter;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\TokenFilter\ScandinavianFoldingTokenFilter;
use TokenizerEngine\TokenFilter\ScandinavianNormalizationTokenFilter;

/**
 * Expected values come from Elasticsearch 9.5.
 */
final class ScandinavianTokenFiltersTest extends TestCase
{
    public function testFolding(): void
    {
        self::assertSame(
            ['blabarsyltetoj', 'blabarsyltetoj', 'Aro', 'Ol', 'Hag'],
            (new ScandinavianFoldingTokenFilter())->filter(['blåbærsyltetøj', 'blaabaersyltetoej', 'Ærø', 'Öl', 'Haag']),
        );
    }

    public function testNormalization(): void
    {
        self::assertSame(
            ['blåbærsyltetøj', 'blåbærsyltetøj', 'ræksmørgås', 'Øl', 'Håg'],
            (new ScandinavianNormalizationTokenFilter())->filter(['blåbærsyltetøj', 'blaabaersyltetoej', 'räksmörgås', 'Öl', 'Haag']),
        );
    }
}
