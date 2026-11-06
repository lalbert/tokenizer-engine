<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Parity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Elasticsearch\AnalysisConfig;
use TokenizerEngine\Token;

/**
 * Compares this library with a real Elasticsearch node.
 *
 * Run with ELASTICSEARCH_URL=http://localhost:9200 composer parity
 * (see tests/Parity/README.md). Without ELASTICSEARCH_URL the tests are
 * skipped, unless PARITY_REQUIRED=1 (CI), in which case they fail.
 */
final class ElasticsearchParityTest extends TestCase
{
    /**
     * @var list<string>|null
     */
    private static ?array $plugins = null;

    private static ?string $version = null;

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function cases(): iterable
    {
        foreach (ParityCases::all() as $name => $case) {
            $texts = isset($case['texts']) ? array_intersect_key(ParityCases::TEXTS, array_flip($case['texts'])) : ParityCases::TEXTS;
            foreach ($texts as $textName => $text) {
                yield $name.' / '.$textName => [$case, $text];
            }
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('cases')]
    public function testSameOutputAsElasticsearch(array $case, string $text): void
    {
        $url = self::url();

        if (isset($case['plugin']) && !\in_array($case['plugin'], self::plugins($url), true)) {
            self::unavailable(\sprintf('Elasticsearch plugin "%s" is not installed.', $case['plugin']));
        }

        $request = array_intersect_key($case, array_flip(['analyzer', 'tokenizer', 'char_filter', 'filter']));
        $expected = self::elasticsearch($url, $request + ['text' => $text]);
        $actual = array_map(static fn (Token $t): array => $t->toArray(), self::library($request)->tokens($text));

        $compare = $case['compare'] ?? ['terms', 'positions', 'offsets'];

        if (\in_array('terms', $compare, true)) {
            self::assertSame(array_column($expected, 'token'), array_column($actual, 'token'), 'Terms differ.');
        } else {
            self::assertCount(\count($expected), $actual, 'Token counts differ.');
        }

        $positionsApply = !isset($case['positions_since']) || version_compare(self::version($url), (string) $case['positions_since'], '>=');
        if (\in_array('positions', $compare, true) && $positionsApply) {
            self::assertSame(array_column($expected, 'position'), array_column($actual, 'position'), 'Positions differ.');
        }

        // Elasticsearch offsets refer to the original text in UTF-16 code
        // units; ours refer to the character-filtered text in code points.
        $comparableOffsets = !isset($request['char_filter']) && !preg_match('/[\x{10000}-\x{10FFFF}]/u', $text);
        if ($comparableOffsets && \in_array('offsets', $compare, true)) {
            self::assertSame(
                array_map(static fn (array $t): array => [$t['start_offset'], $t['end_offset']], $expected),
                array_map(static fn (array $t): array => [$t['start_offset'], $t['end_offset']], $actual),
                'Offsets differ.',
            );
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function library(array $request): \TokenizerEngine\TokenizerEngine
    {
        if (isset($request['analyzer'])) {
            return (new AnalysisConfig([]))->analyzer((string) $request['analyzer']);
        }

        return (new AnalysisConfig(['analyzer' => ['parity' => ['type' => 'custom'] + $request]], null, true))->analyzer('parity');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<array{token: string, start_offset: int, end_offset: int, type: string, position: int}>
     */
    private static function elasticsearch(string $url, array $body): array
    {
        $response = self::request($url.'/_analyze', $body);

        if (!isset($response['tokens']) || !\is_array($response['tokens'])) {
            self::fail('Unexpected Elasticsearch response: '.json_encode($response));
        }

        /** @var list<array{token: string, start_offset: int, end_offset: int, type: string, position: int}> */
        return $response['tokens'];
    }

    private static function version(string $url): string
    {
        if (null === self::$version) {
            $info = self::request($url);
            $version = \is_array($info['version'] ?? null) ? ($info['version']['number'] ?? null) : null;
            self::$version = \is_string($version) ? $version : '0';
        }

        return self::$version;
    }

    /**
     * @return list<string>
     */
    private static function plugins(string $url): array
    {
        if (null === self::$plugins) {
            $plugins = self::request($url.'/_cat/plugins?format=json');
            self::$plugins = array_values(array_unique(array_map(static fn (array $p): string => (string) $p['component'], $plugins)));
        }

        return self::$plugins;
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>
     */
    private static function request(string $url, ?array $body = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => null === $body ? 'GET' : 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => null === $body ? '' : json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);

        $raw = @file_get_contents($url, false, $context);
        if (false === $raw) {
            self::fail(\sprintf('Cannot reach Elasticsearch at %s.', $url));
        }

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private static function url(): string
    {
        $url = getenv('ELASTICSEARCH_URL');
        if (false === $url || '' === $url) {
            self::unavailable('ELASTICSEARCH_URL is not set.');
        }

        return rtrim($url, '/');
    }

    private static function unavailable(string $reason): never
    {
        if ('1' === getenv('PARITY_REQUIRED')) {
            self::fail($reason);
        }

        self::markTestSkipped($reason);
    }
}
