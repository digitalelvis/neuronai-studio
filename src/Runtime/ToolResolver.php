<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Tools\WebhookTool;
use Illuminate\Support\Str;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Tools\ProviderTool;
use NeuronAI\Tools\ProviderToolInterface;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\AbstractToolkit;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class ToolResolver
{
    public function __construct(
        protected ToolRegistry $registry,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $bindings
     * @return array<int, ToolInterface|ToolkitInterface|ProviderToolInterface>
     */
    public function resolveMany(array $bindings): array
    {
        $resolved = [];

        foreach ($bindings as $binding) {
            if (! is_array($binding) || empty($binding['ref'])) {
                continue;
            }

            foreach ($this->resolve($binding['ref'], $binding) as $tool) {
                $resolved[] = $tool;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $binding
     * @return array<int, ToolInterface|ToolkitInterface|ProviderToolInterface>
     */
    public function resolve(string $ref, array $binding = []): array
    {
        $config = array_merge(
            $this->registry->configFor($ref),
            $binding['config'] ?? [],
        );

        if (str_starts_with($ref, 'toolkit:')) {
            return [$this->resolveToolkit($config, $binding)];
        }

        if (str_starts_with($ref, 'class:')) {
            return [$this->resolveClass($config['class'] ?? Str::after($ref, 'class:'), $config)];
        }

        if (str_starts_with($ref, 'tool:db:')) {
            return [$this->resolveDatabaseTool((int) Str::after($ref, 'tool:db:'))];
        }

        if (str_starts_with($ref, 'mcp:')) {
            return $this->resolveMcp($ref, $binding, $config);
        }

        if (str_starts_with($ref, 'provider:')) {
            return [$this->resolveProviderTool($config)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $binding
     */
    protected function resolveToolkit(array $config, array $binding): ToolkitInterface
    {
        $class = $config['class'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new \InvalidArgumentException('Toolkit class not configured or not found.');
        }

        $constructorArgs = $this->resolveConfigValues($config['constructor'] ?? []);
        $toolkit = empty($constructorArgs)
            ? $class::make()
            : new $class(...$constructorArgs);

        if ($toolkit instanceof AbstractToolkit) {
            if (! empty($binding['exclude'])) {
                $toolkit->exclude($binding['exclude']);
            }

            if (! empty($binding['only'])) {
                $toolkit->only($binding['only']);
            }
        }

        return $toolkit;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveClass(string $class, array $config): ToolInterface
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Tool class [{$class}] not found.");
        }

        $constructorArgs = $this->resolveConfigValues($config['constructor'] ?? []);

        if (method_exists($class, 'make') && empty($constructorArgs)) {
            return $class::make();
        }

        if (empty($constructorArgs)) {
            return app($class);
        }

        return new $class(...$constructorArgs);
    }

    protected function resolveDatabaseTool(int $id): ToolInterface
    {
        $definition = ToolDefinition::findOrFail($id);

        if (in_array($definition->type, ['builder', 'codegen'], true) && ! empty($definition->config['class_path'])) {
            return $this->resolveClass($definition->config['class_path'], $definition->config);
        }

        return WebhookTool::fromDefinition($definition);
    }

    /**
     * @param  array<string, mixed>  $binding
     * @param  array<string, mixed>  $config
     * @return array<int, ToolInterface>
     */
    protected function resolveMcp(string $ref, array $binding, array $config): array
    {
        $connectorConfig = $config['connector'] ?? $config;

        if (empty($connectorConfig)) {
            return [];
        }

        $connector = McpConnector::make($this->resolveConfigValues($connectorConfig));

        if (! empty($binding['exclude'])) {
            $connector->exclude($binding['exclude']);
        }

        if (! empty($binding['only'])) {
            $connector->only($binding['only']);
        }

        return $connector->tools();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveProviderTool(array $config): ProviderToolInterface
    {
        $type = $config['type'] ?? 'web_search';
        $tool = ProviderTool::make($type);

        if (! empty($config['options'])) {
            $tool->setOptions($this->resolveConfigValues($config['options']));
        }

        return $tool;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, mixed>
     */
    protected function resolveConfigValues(array $values): array
    {
        return array_map(fn ($value) => $this->resolveConfigValue($value), $values);
    }

    protected function resolveConfigValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return is_array($value)
                ? array_map(fn ($item) => $this->resolveConfigValue($item), $value)
                : $value;
        }

        if (str_starts_with($value, 'env:')) {
            return env(Str::after($value, 'env:'), '');
        }

        if (preg_match('/^\{\{\s*env\.([A-Z0-9_]+)\s*\}\}$/', $value, $matches)) {
            return env($matches[1], '');
        }

        return $value;
    }
}
