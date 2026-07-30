<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpEndpointBinding;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Support\ToolSchemaInspector;
use Illuminate\Support\Str;

class McpToolCatalog
{
    public function __construct(
        protected ToolSchemaInspector $inspector,
    ) {}

    /**
     * Flatten enabled bindings into MCP tool definitions.
     *
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, mixed>,
     *     source: array<string, mixed>
     * }>
     */
    public function toolsFor(McpEndpoint $endpoint): array
    {
        $endpoint->loadMissing('bindings');
        $tools = [];
        $usedNames = [];

        foreach ($endpoint->bindings->where('enabled', true)->values() as $binding) {
            foreach ($this->expandBinding($binding) as $tool) {
                $name = $this->uniqueName($tool['name'], $usedNames);
                $usedNames[$name] = true;
                $tool['name'] = $name;
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, mixed>,
     *     source: array<string, mixed>
     * }>
     */
    public function toolsByName(McpEndpoint $endpoint): array
    {
        $map = [];

        foreach ($this->toolsFor($endpoint) as $tool) {
            $map[$tool['name']] = $tool;
        }

        return $map;
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, mixed>,
     *     source: array<string, mixed>
     * }>
     */
    protected function expandBinding(McpEndpointBinding $binding): array
    {
        return match ($binding->kind) {
            McpEndpointBinding::KIND_TOOL => $this->expandTool($binding),
            McpEndpointBinding::KIND_TOOLKIT => $this->expandToolkit($binding),
            McpEndpointBinding::KIND_AGENT => $this->expandAgent($binding),
            McpEndpointBinding::KIND_WORKFLOW => $this->expandWorkflow($binding),
            default => [],
        };
    }

    /**
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, source: array<string, mixed>}>
     */
    protected function expandTool(McpEndpointBinding $binding): array
    {
        $ref = $binding->ref;
        $entry = [
            'ref' => $ref,
            'label' => $binding->tool_name ?: $ref,
            'type' => 'tool',
            'category' => 'studio',
            'description' => $binding->tool_description,
        ];

        $enriched = $this->inspector->enrich($entry);
        $actions = $enriched['actions'] ?? [];

        if ($actions === []) {
            $name = $this->defaultToolName($binding);
            $description = $binding->tool_description
                ?: $this->toolDefinitionDescription($ref)
                ?: "Studio tool {$ref}";

            return [[
                'name' => $name,
                'description' => $description,
                'inputSchema' => $this->objectSchema([]),
                'source' => [
                    'kind' => McpEndpointBinding::KIND_TOOL,
                    'ref' => $ref,
                    'binding_id' => $binding->id,
                ],
            ]];
        }

        $action = $actions[0];
        $name = $binding->tool_name ?: $this->sanitizeName((string) ($action['name'] ?? $this->defaultToolName($binding)));
        $description = $binding->tool_description
            ?: (string) ($action['description'] ?? '')
            ?: "Studio tool {$name}";

        return [[
            'name' => $name,
            'description' => $description,
            'inputSchema' => $this->propertiesToSchema($action['properties'] ?? []),
            'source' => [
                'kind' => McpEndpointBinding::KIND_TOOL,
                'ref' => $ref,
                'binding_id' => $binding->id,
            ],
        ]];
    }

    /**
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, source: array<string, mixed>}>
     */
    protected function expandToolkit(McpEndpointBinding $binding): array
    {
        $ref = $binding->ref;
        $enriched = $this->inspector->enrich([
            'ref' => $ref,
            'label' => $ref,
            'type' => 'toolkit',
            'category' => 'builtin',
            'description' => $binding->tool_description,
        ]);

        $only = is_array($binding->only) ? array_values(array_filter($binding->only, 'is_string')) : [];
        $exclude = is_array($binding->exclude) ? array_values(array_filter($binding->exclude, 'is_string')) : [];

        $tools = [];

        foreach ($enriched['actions'] ?? [] as $action) {
            $childName = (string) ($action['name'] ?? '');
            if ($childName === '') {
                continue;
            }

            if ($only !== [] && ! in_array($childName, $only, true)) {
                continue;
            }

            if ($exclude !== [] && in_array($childName, $exclude, true)) {
                continue;
            }

            $mcpName = $binding->tool_name
                ? $this->sanitizeName($binding->tool_name.'_'.$childName)
                : $this->sanitizeName($childName);

            $tools[] = [
                'name' => $mcpName,
                'description' => (string) ($action['description'] ?? $childName),
                'inputSchema' => $this->propertiesToSchema($action['properties'] ?? []),
                'source' => [
                    'kind' => McpEndpointBinding::KIND_TOOLKIT,
                    'ref' => $ref,
                    'child_tool' => $childName,
                    'binding_id' => $binding->id,
                    'only' => $only,
                    'exclude' => $exclude,
                ],
            ];
        }

        return $tools;
    }

    /**
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, source: array<string, mixed>}>
     */
    protected function expandAgent(McpEndpointBinding $binding): array
    {
        $agent = $this->resolveAgent($binding->ref);

        if (! $agent) {
            return [];
        }

        $name = $binding->tool_name
            ?: $this->sanitizeName($agent->slug ?: $agent->name);
        $description = $binding->tool_description
            ?: (string) ($agent->description ?: "Run agent {$agent->name}");

        return [[
            'name' => $name,
            'description' => $description,
            'inputSchema' => $this->objectSchema([
                'message' => [
                    'type' => 'string',
                    'description' => 'User message for the agent',
                ],
            ], ['message']),
            'source' => [
                'kind' => McpEndpointBinding::KIND_AGENT,
                'ref' => (string) $agent->id,
                'agent_id' => $agent->id,
                'binding_id' => $binding->id,
            ],
        ]];
    }

    /**
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, source: array<string, mixed>}>
     */
    protected function expandWorkflow(McpEndpointBinding $binding): array
    {
        $workflow = $this->resolveWorkflow($binding->ref);

        if (! $workflow) {
            return [];
        }

        $name = $binding->tool_name
            ?: $this->sanitizeName($workflow->slug ?: $workflow->name);
        $description = $binding->tool_description
            ?: (string) ($workflow->description ?: "Run workflow {$workflow->name}");

        $properties = [
            'input' => [
                'type' => 'object',
                'description' => 'Workflow input payload (merged into run state)',
            ],
        ];

        $schema = is_array($workflow->state_schema) ? $workflow->state_schema : [];
        foreach ($schema as $key => $definition) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $properties[$key] = [
                'type' => is_array($definition) ? (string) ($definition['type'] ?? 'string') : 'string',
                'description' => is_array($definition)
                    ? (string) ($definition['description'] ?? $key)
                    : (string) $definition,
            ];
        }

        return [[
            'name' => $name,
            'description' => $description,
            'inputSchema' => $this->objectSchema($properties),
            'source' => [
                'kind' => McpEndpointBinding::KIND_WORKFLOW,
                'ref' => (string) $workflow->id,
                'workflow_id' => $workflow->id,
                'binding_id' => $binding->id,
            ],
        ]];
    }

    protected function resolveAgent(string $ref): ?AgentDefinition
    {
        if (ctype_digit($ref)) {
            return AgentDefinition::find((int) $ref);
        }

        if (str_starts_with($ref, 'agent:')) {
            $id = Str::after($ref, 'agent:');

            return ctype_digit($id)
                ? AgentDefinition::find((int) $id)
                : AgentDefinition::query()->where('slug', $id)->first();
        }

        return AgentDefinition::query()->where('slug', $ref)->first();
    }

    protected function resolveWorkflow(string $ref): ?WorkflowDefinition
    {
        if (ctype_digit($ref)) {
            return WorkflowDefinition::find((int) $ref);
        }

        if (str_starts_with($ref, 'workflow:')) {
            $id = Str::after($ref, 'workflow:');

            return ctype_digit($id)
                ? WorkflowDefinition::find((int) $id)
                : WorkflowDefinition::query()->where('slug', $id)->first();
        }

        return WorkflowDefinition::query()->where('slug', $ref)->first();
    }

    protected function toolDefinitionDescription(string $ref): ?string
    {
        if (! str_starts_with($ref, 'tool:db:')) {
            return null;
        }

        $definition = ToolDefinition::find((int) Str::after($ref, 'tool:db:'));

        return $definition?->description;
    }

    protected function defaultToolName(McpEndpointBinding $binding): string
    {
        if ($binding->tool_name) {
            return $this->sanitizeName($binding->tool_name);
        }

        if (str_starts_with($binding->ref, 'tool:db:')) {
            $definition = ToolDefinition::find((int) Str::after($binding->ref, 'tool:db:'));

            if ($definition) {
                return $this->sanitizeName((string) ($definition->config['tool_name'] ?? $definition->slug ?: $definition->name));
            }
        }

        return $this->sanitizeName(str_replace([':', '/'], '_', $binding->ref));
    }

    /**
     * @param  array<int, array{name: string, type?: string, description?: string|null, required?: bool}>  $properties
     * @return array<string, mixed>
     */
    protected function propertiesToSchema(array $properties): array
    {
        $props = [];
        $required = [];

        foreach ($properties as $property) {
            $name = (string) ($property['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $props[$name] = [
                'type' => $this->mapJsonType((string) ($property['type'] ?? 'string')),
                'description' => (string) ($property['description'] ?? $name),
            ];

            if (! empty($property['required'])) {
                $required[] = $name;
            }
        }

        return $this->objectSchema($props, $required);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    protected function objectSchema(array $properties, array $required = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    protected function mapJsonType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    protected function sanitizeName(string $name): string
    {
        $name = Str::snake(preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $name) ?? $name);
        $name = trim($name, '_');

        return $name !== '' ? $name : 'tool';
    }

    /**
     * @param  array<string, true>  $used
     */
    protected function uniqueName(string $name, array $used): string
    {
        if (! isset($used[$name])) {
            return $name;
        }

        $i = 2;
        while (isset($used["{$name}_{$i}"])) {
            $i++;
        }

        return "{$name}_{$i}";
    }
}
