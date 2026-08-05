<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

use Illuminate\Support\Arr;
use NeuronAI\Workflow\WorkflowState;

/**
 * Immutable snapshot of runtime state available to tools that opt into
 * {@see ToolContextAware}. Never merged into the LLM tool schema.
 */
final class ToolContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private readonly array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::filterInternalKeys($data));
    }

    public static function fromWorkflowState(WorkflowState $state): self
    {
        return self::fromArray($state->all());
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    /**
     * Merge another context on top (later keys win). Internal keys remain filtered.
     */
    public function merge(self $other): self
    {
        return self::fromArray(array_merge($this->data, $other->data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filterInternalKeys(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (str_starts_with($key, '__')) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
