<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use Illuminate\Support\Str;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolPropertyInterface;
use NeuronAI\Tools\Toolkits\AbstractToolkit;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class ToolSchemaInspector
{
    public function __construct(
        protected ToolRegistry $registry,
    ) {}

    /**
     * @param  array{ref: string, label: string, type: string, category: string, description: string|null}  $entry
     * @return array{
     *     ref: string,
     *     label: string,
     *     type: string,
     *     category: string,
     *     description: string|null,
     *     editable: bool,
     *     actions: array<int, array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}>
     * }
     */
    public function enrich(array $entry): array
    {
        $ref = (string) ($entry['ref'] ?? '');
        $editable = str_starts_with($ref, 'tool:db:');

        return [
            'ref' => $ref,
            'label' => $entry['label'] ?? $ref,
            'type' => $entry['type'] ?? 'tool',
            'category' => $entry['category'] ?? 'builtin',
            'description' => $entry['description'] ?? null,
            'editable' => $editable,
            'actions' => $this->actionsFor($ref, $entry),
        ];
    }

    /**
     * @param  array{ref?: string, label?: string, type?: string, category?: string, description?: string|null}  $entry
     * @return array<int, array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}>
     */
    protected function actionsFor(string $ref, array $entry): array
    {
        try {
            if (str_starts_with($ref, 'tool:db:')) {
                return $this->actionsFromDatabase((int) Str::after($ref, 'tool:db:'));
            }

            if (str_starts_with($ref, 'mcp:')) {
                return [];
            }

            if (str_starts_with($ref, 'toolkit:')) {
                return $this->actionsFromToolkit($ref);
            }

            if (str_starts_with($ref, 'class:')) {
                return $this->actionsFromClass(Str::after($ref, 'class:'));
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @return array<int, array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}>
     */
    protected function actionsFromDatabase(int $id): array
    {
        $definition = ToolDefinition::find($id);

        if (! $definition) {
            return [];
        }

        $name = (string) ($definition->config['tool_name'] ?? $definition->slug ?? $definition->name);
        $properties = [];

        foreach ($definition->input_schema ?? [] as $property) {
            if (! is_array($property) || empty($property['name'])) {
                continue;
            }

            $properties[] = [
                'name' => (string) $property['name'],
                'type' => (string) ($property['type'] ?? 'string'),
                'description' => isset($property['description']) ? (string) $property['description'] : null,
                'required' => (bool) ($property['required'] ?? false),
            ];
        }

        return [[
            'name' => $name,
            'description' => $definition->description,
            'properties' => $properties,
        ]];
    }

    /**
     * @return array<int, array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}>
     */
    protected function actionsFromToolkit(string $ref): array
    {
        $config = $this->registry->configFor($ref);
        $class = $config['class'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return [];
        }

        $constructor = is_array($config['constructor'] ?? null) ? $config['constructor'] : [];

        if ($constructor !== []) {
            return [];
        }

        $toolkit = method_exists($class, 'make') ? $class::make() : new $class;

        if (! $toolkit instanceof ToolkitInterface) {
            return [];
        }

        $tools = $toolkit instanceof AbstractToolkit
            ? $toolkit->tools()
            : ($toolkit->tools() ?? []);

        return array_values(array_map(
            fn (ToolInterface $tool) => $this->serializeTool($tool),
            $tools,
        ));
    }

    /**
     * @return array<int, array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}>
     */
    protected function actionsFromClass(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $tool = method_exists($class, 'make') ? $class::make() : app($class);

        if (! $tool instanceof ToolInterface) {
            return [];
        }

        return [$this->serializeTool($tool)];
    }

    /**
     * @return array{name: string, description: string|null, properties: array<int, array{name: string, type: string, description: string|null, required: bool}>}
     */
    protected function serializeTool(ToolInterface $tool): array
    {
        $properties = [];

        foreach ($tool->getProperties() as $property) {
            if (! $property instanceof ToolPropertyInterface) {
                continue;
            }

            $serialized = $property->jsonSerialize();
            $properties[] = [
                'name' => (string) ($serialized['name'] ?? $property->getName()),
                'type' => (string) ($serialized['type'] ?? $property->getType()->value),
                'description' => isset($serialized['description'])
                    ? (string) $serialized['description']
                    : $property->getDescription(),
                'required' => (bool) ($serialized['required'] ?? $property->isRequired()),
            ];
        }

        return [
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'properties' => $properties,
        ];
    }
}
