<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use InvalidArgumentException;

/**
 * Per-agent memory envelope stored in AgentDefinition.memory_config.
 * Null/empty = inherit global defaults (today's runtime behavior).
 * Unknown keys are ignored for forward compatibility.
 */
final class MemoryConfig
{
    public const DRIVER_ELOQUENT = 'eloquent';

    public const DRIVER_IN_MEMORY = 'in_memory';

    public const DRIVERS = [
        self::DRIVER_ELOQUENT,
        self::DRIVER_IN_MEMORY,
    ];

    public function __construct(
        private readonly ?int $contextWindow = null,
        private readonly ?string $driver = null,
        private readonly ?bool $summarizationEnabled = null,
        private readonly ?float $summarizationThreshold = null,
        private readonly ?int $budgetRag = null,
        private readonly ?int $budgetToolResults = null,
        private readonly ?int $budgetState = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null || $data === []) {
            return new self;
        }

        $contextWindow = self::optionalPositiveInt($data, 'context_window');
        $driver = self::optionalDriver($data);
        $summarizationEnabled = self::optionalBool($data, 'summarization_enabled');
        $summarizationThreshold = self::optionalThreshold($data);
        $budgetRag = self::optionalPositiveInt($data, 'budget_rag');
        $budgetToolResults = self::optionalPositiveInt($data, 'budget_tool_results');
        $budgetState = self::optionalPositiveInt($data, 'budget_state');

        return new self(
            contextWindow: $contextWindow,
            driver: $driver,
            summarizationEnabled: $summarizationEnabled,
            summarizationThreshold: $summarizationThreshold,
            budgetRag: $budgetRag,
            budgetToolResults: $budgetToolResults,
            budgetState: $budgetState,
        );
    }

    /**
     * Laravel validation rules for form / API payloads.
     *
     * @return array<string, list<string>>
     */
    public static function validationRules(string $prefix = 'memory_config'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.context_window" => ['nullable', 'integer', 'min:1'],
            "{$prefix}.driver" => ['nullable', 'string', 'in:'.implode(',', self::DRIVERS)],
            "{$prefix}.summarization_enabled" => ['nullable', 'boolean'],
            "{$prefix}.summarization_threshold" => ['nullable', 'numeric', 'gt:0', 'lte:1'],
            "{$prefix}.budget_rag" => ['nullable', 'integer', 'min:1'],
            "{$prefix}.budget_tool_results" => ['nullable', 'integer', 'min:1'],
            "{$prefix}.budget_state" => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function isInherit(): bool
    {
        return $this->toArray() === null;
    }

    public function contextWindow(): ?int
    {
        return $this->contextWindow;
    }

    public function driver(): ?string
    {
        return $this->driver;
    }

    public function summarizationEnabled(): ?bool
    {
        return $this->summarizationEnabled;
    }

    public function summarizationThreshold(): ?float
    {
        return $this->summarizationThreshold;
    }

    public function budgetRag(): ?int
    {
        return $this->budgetRag;
    }

    public function budgetToolResults(): ?int
    {
        return $this->budgetToolResults;
    }

    public function budgetState(): ?int
    {
        return $this->budgetState;
    }

    /**
     * Overlay non-null fields from $override onto this config.
     */
    public function merge(self $override): self
    {
        return new self(
            contextWindow: $override->contextWindow ?? $this->contextWindow,
            driver: $override->driver ?? $this->driver,
            summarizationEnabled: $override->summarizationEnabled ?? $this->summarizationEnabled,
            summarizationThreshold: $override->summarizationThreshold ?? $this->summarizationThreshold,
            budgetRag: $override->budgetRag ?? $this->budgetRag,
            budgetToolResults: $override->budgetToolResults ?? $this->budgetToolResults,
            budgetState: $override->budgetState ?? $this->budgetState,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(): ?array
    {
        $out = [];

        if ($this->contextWindow !== null) {
            $out['context_window'] = $this->contextWindow;
        }
        if ($this->driver !== null) {
            $out['driver'] = $this->driver;
        }
        if ($this->summarizationEnabled !== null) {
            $out['summarization_enabled'] = $this->summarizationEnabled;
        }
        if ($this->summarizationThreshold !== null) {
            $out['summarization_threshold'] = $this->summarizationThreshold;
        }
        if ($this->budgetRag !== null) {
            $out['budget_rag'] = $this->budgetRag;
        }
        if ($this->budgetToolResults !== null) {
            $out['budget_tool_results'] = $this->budgetToolResults;
        }
        if ($this->budgetState !== null) {
            $out['budget_state'] = $this->budgetState;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalPositiveInt(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        if (! is_numeric($data[$key]) || (int) $data[$key] != $data[$key]) {
            throw new InvalidArgumentException("memory_config.{$key} must be a positive integer.");
        }

        $value = (int) $data[$key];
        if ($value < 1) {
            throw new InvalidArgumentException("memory_config.{$key} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalDriver(array $data): ?string
    {
        if (! array_key_exists('driver', $data) || $data['driver'] === null || $data['driver'] === '') {
            return null;
        }

        $driver = (string) $data['driver'];
        if (! in_array($driver, self::DRIVERS, true)) {
            throw new InvalidArgumentException(
                'memory_config.driver must be one of: '.implode(', ', self::DRIVERS).'.'
            );
        }

        return $driver;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalBool(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? (bool) $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalThreshold(array $data): ?float
    {
        if (! array_key_exists('summarization_threshold', $data)
            || $data['summarization_threshold'] === null
            || $data['summarization_threshold'] === '') {
            return null;
        }

        if (! is_numeric($data['summarization_threshold'])) {
            throw new InvalidArgumentException(
                'memory_config.summarization_threshold must be a number greater than 0 and at most 1.'
            );
        }

        $value = (float) $data['summarization_threshold'];
        if ($value <= 0 || $value > 1) {
            throw new InvalidArgumentException(
                'memory_config.summarization_threshold must be a number greater than 0 and at most 1.'
            );
        }

        return $value;
    }
}
