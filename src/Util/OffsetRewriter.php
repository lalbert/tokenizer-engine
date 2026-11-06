<?php

declare(strict_types=1);

namespace TokenizerEngine\Util;

use TokenizerEngine\OffsetMap;

/**
 * Replaces regular expression matches while recording the offset
 * corrections, so that offsets of the result can be mapped back.
 *
 * @internal
 */
final class OffsetRewriter
{
    /**
     * @param callable(array<int|string, string>): (array{0: string, 1: bool}|null) $replace
     *                                                                                       returns [replacement, is markup], or null to keep the match
     *
     * @return array{0: string, 1: OffsetMap}
     */
    public static function rewrite(string $text, string $regex, callable $replace): array
    {
        if (false === preg_match_all($regex, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE)) {
            throw new \InvalidArgumentException('Input is not valid UTF-8.');
        }

        if ([] === $matches) {
            return [$text, OffsetMap::identity()];
        }

        $output = '';
        $byte = 0;      // position in $text
        $inChar = 0;    // characters of $text consumed
        $outChar = 0;   // characters written to $output
        $diff = 0;
        $corrections = [];
        $markup = [];

        foreach ($matches as $match) {
            [$value, $start] = $match[0];
            $groups = array_map(static fn (array $g): string => $g[0], $match);
            $result = $replace($groups);
            if (null === $result) {
                continue;
            }
            [$replacement, $isMarkup] = $result;

            $before = substr($text, $byte, $start - $byte);
            $beforeLength = mb_strlen($before, 'UTF-8');
            $output .= $before.$replacement;
            $inChar += $beforeLength;
            $outChar += $beforeLength;

            $inLength = mb_strlen($value, 'UTF-8');
            $outLength = mb_strlen($replacement, 'UTF-8');

            if ($isMarkup) {
                $markup[] = [$inChar, $inChar + $inLength];
            }

            $inChar += $inLength;
            $outChar += $outLength;
            if ($inLength !== $outLength) {
                $diff += $inLength - $outLength;
                $corrections[$outChar] = $diff;
            }

            $byte = $start + \strlen($value);
        }

        $output .= substr($text, $byte);

        return [$output, OffsetMap::fromCorrections($corrections, $markup)];
    }
}
