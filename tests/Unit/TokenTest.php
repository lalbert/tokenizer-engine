<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TokenizerEngine\Token;

final class TokenTest extends TestCase
{
    public function testImmutability(): void
    {
        $token = new Token('Foo', 2, 4, 7);
        $copy = $token->withTerm('foo');

        self::assertSame('Foo', $token->term);
        self::assertSame(['token' => 'foo', 'start_offset' => 4, 'end_offset' => 7, 'type' => 'word', 'position' => 2], $copy->toArray());
        self::assertSame('foo', (string) $copy);
    }

    public function testExactOffsets(): void
    {
        self::assertTrue((new Token('été', 0, 2, 5))->hasExactOffsets());
        self::assertFalse((new Token('ete', 0, 2, 6))->hasExactOffsets());
        self::assertFalse((new Token('ete'))->hasExactOffsets());
    }
}
