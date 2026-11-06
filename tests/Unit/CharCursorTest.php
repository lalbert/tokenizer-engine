<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Util\CharCursor;

final class CharCursorTest extends TestCase
{
    public function testSameResultsAsMbSubstrInAnyOrder(): void
    {
        $text = str_repeat('Élan été 東京 🙂 a', 50);
        $length = mb_strlen($text);
        $ranges = [[0, 3], [5, 9], [2, 4], [100, 180], [$length - 3, $length], [0, $length], [7, 7]];

        $cursor = new CharCursor($text);
        foreach ($ranges as [$start, $end]) {
            self::assertSame(mb_substr($text, $start, $end - $start), $cursor->substring($start, $end));
        }

        $prepared = (new CharCursor($text))->prepare(array_merge(...$ranges));
        foreach (array_reverse($ranges) as [$start, $end]) {
            self::assertSame(mb_substr($text, $start, $end - $start), $prepared->substring($start, $end));
        }
    }
}
