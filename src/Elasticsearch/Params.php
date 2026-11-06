<?php

declare(strict_types=1);

namespace TokenizerEngine\Elasticsearch;

use TokenizerEngine\TokenFilter\TokenFilterInterface;

/**
 * Typed access to the parameters of an Elasticsearch component definition.
 * Accepts Elasticsearch's lenient values ("true", "3").
 */
final class Params
{
    /**
     * @var array<string, true>
     */
    private array $read = ['type' => true];

    /**
     * @param array<string, mixed>                          $params
     * @param (\Closure(string): TokenFilterInterface)|null $filterResolver resolves filter names (custom or built-in)
     */
    public function __construct(
        private readonly array $params = [],
        private readonly string $component = 'component',
        private readonly ?\Closure $filterResolver = null,
    ) {
    }

    /**
     * Builds a token filter referenced by name (used by `multiplexer`).
     */
    public function filter(string $name): TokenFilterInterface
    {
        if (null === $this->filterResolver) {
            throw new \LogicException(\sprintf('%s cannot reference other filters here.', $this->component));
        }

        return ($this->filterResolver)($name);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->params);
    }

    public function string(string $name, ?string $default = null): ?string
    {
        $this->read[$name] = true;
        $value = $this->params[$name] ?? $default;

        if (null !== $value && !\is_scalar($value)) {
            throw $this->invalid($name, 'a string');
        }

        return null === $value ? null : (string) $value;
    }

    public function int(string $name, int $default): int
    {
        $this->read[$name] = true;
        $value = $this->params[$name] ?? $default;

        if (\is_int($value) || (\is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return (int) $value;
        }

        throw $this->invalid($name, 'an integer');
    }

    public function bool(string $name, bool $default): bool
    {
        $this->read[$name] = true;
        $value = $this->params[$name] ?? $default;

        return match ($value) {
            true, 'true' => true,
            false, 'false' => false,
            default => throw $this->invalid($name, 'a boolean'),
        };
    }

    /**
     * @param list<string>|null $default
     *
     * @return list<string>|null
     */
    public function list(string $name, ?array $default = null): ?array
    {
        $this->read[$name] = true;
        $value = $this->params[$name] ?? $default;

        if (null === $value) {
            return null;
        }

        if (\is_string($value)) {
            return [$value];
        }

        if (!\is_array($value)) {
            throw $this->invalid($name, 'a list of strings');
        }

        return array_values(array_map('strval', $value));
    }

    /**
     * A predefined list name ("_english_") or a list of words.
     *
     * @return string|list<string>|null
     */
    public function stringOrList(string $name, string|array|null $default = null): string|array|null
    {
        $this->read[$name] = true;
        $value = $this->params[$name] ?? $default;

        return \is_string($value) || null === $value ? $value : $this->list($name);
    }

    /**
     * @return list<string> parameters that were never read
     */
    public function unused(): array
    {
        return array_values(array_diff(array_keys($this->params), array_keys($this->read)));
    }

    private function invalid(string $name, string $expected): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf('Parameter "%s" of %s must be %s.', $name, $this->component, $expected));
    }
}
