<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\McpEndpoints;

use DigitalElvis\NeuronAIStudio\McpServer\McpToolCatalog;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpEndpointBinding;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Support\ResolvesOptionalRouteModel;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;

class Edit extends Component
{
    use ResolvesOptionalRouteModel;

    public ?McpEndpoint $endpoint = null;

    public string $name = '';

    public string $description = '';

    public bool $enabled = false;

    public int $timeoutSeconds = 180;

    /** @var array<int, array{kind: string, ref: string, tool_name: string, tool_description: string, only: array<int, string>, exclude: array<int, string>, enabled: bool}> */
    public array $bindings = [];

    public ?string $plainApiKey = null;

    public string $apiKeyPrefix = '';

    public string $activeTab = 'general';

    public function mount(mixed $endpoint = null): void
    {
        $this->endpoint = $this->resolveOptionalRouteModel($endpoint, McpEndpoint::class);
        $this->timeoutSeconds = (int) config('neuronai-studio.mcp_endpoints.default_timeout_seconds', 180);

        if ($this->endpoint?->exists) {
            $this->name = $this->endpoint->name;
            $this->description = (string) $this->endpoint->description;
            $this->enabled = (bool) $this->endpoint->enabled;
            $this->timeoutSeconds = (int) $this->endpoint->timeout_seconds;
            $this->apiKeyPrefix = (string) ($this->endpoint->api_key_prefix ?? '');

            $this->bindings = $this->endpoint->bindings->map(fn (McpEndpointBinding $binding) => [
                'kind' => $binding->kind,
                'ref' => $binding->ref,
                'tool_name' => (string) ($binding->tool_name ?? ''),
                'tool_description' => (string) ($binding->tool_description ?? ''),
                'only' => is_array($binding->only) ? array_values($binding->only) : [],
                'exclude' => is_array($binding->exclude) ? array_values($binding->exclude) : [],
                'enabled' => (bool) $binding->enabled,
            ])->all();
        }

        if (session()->has('mcp_endpoint_plain_key')) {
            $this->plainApiKey = (string) session('mcp_endpoint_plain_key');
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['general', 'bindings', 'connect'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function toggleBinding(string $kind, string $ref): void
    {
        foreach ($this->bindings as $index => $binding) {
            if ($binding['kind'] === $kind && $binding['ref'] === $ref) {
                unset($this->bindings[$index]);
                $this->bindings = array_values($this->bindings);

                return;
            }
        }

        $this->bindings[] = [
            'kind' => $kind,
            'ref' => $ref,
            'tool_name' => '',
            'tool_description' => '',
            'only' => [],
            'exclude' => [],
            'enabled' => true,
        ];
    }

    public function isBound(string $kind, string $ref): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding['kind'] === $kind && $binding['ref'] === $ref) {
                return true;
            }
        }

        return false;
    }

    public function updateBindingMeta(string $kind, string $ref, string $field, string $value): void
    {
        foreach ($this->bindings as $index => $binding) {
            if ($binding['kind'] === $kind && $binding['ref'] === $ref) {
                if (in_array($field, ['tool_name', 'tool_description'], true)) {
                    $this->bindings[$index][$field] = $value;
                }

                return;
            }
        }
    }

    public function updateToolkitFilter(string $ref, string $field, string $csv): void
    {
        $values = array_values(array_filter(array_map('trim', explode(',', $csv)), fn ($v) => $v !== ''));

        foreach ($this->bindings as $index => $binding) {
            if ($binding['kind'] === McpEndpointBinding::KIND_TOOLKIT && $binding['ref'] === $ref) {
                if (in_array($field, ['only', 'exclude'], true)) {
                    $this->bindings[$index][$field] = $values;
                }

                return;
            }
        }
    }

    public function rotateKey(): void
    {
        if (! $this->endpoint?->exists) {
            $this->addError('name', __('neuronai-studio::flash.mcp_endpoint_save_before_key'));

            return;
        }

        $this->plainApiKey = $this->endpoint->rotateApiKey();
        $this->apiKeyPrefix = (string) $this->endpoint->api_key_prefix;
        session()->flash('success', __('neuronai-studio::flash.mcp_endpoint_key_rotated'));
        session()->flash('mcp_endpoint_plain_key', $this->plainApiKey);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'timeoutSeconds' => 'required|integer|min:1|max:3600',
            'bindings' => 'array',
            'bindings.*.kind' => 'required|in:tool,toolkit,agent,workflow',
            'bindings.*.ref' => 'required|string|max:255',
            'bindings.*.tool_name' => 'nullable|string|max:128',
            'bindings.*.tool_description' => 'nullable|string',
            'bindings.*.enabled' => 'boolean',
        ]);

        $slug = Str::slug($validated['name']);

        $payload = [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'enabled' => (bool) $validated['enabled'],
            'timeout_seconds' => (int) $validated['timeoutSeconds'],
        ];

        $creating = ! $this->endpoint?->exists;

        if ($creating) {
            $this->endpoint = McpEndpoint::create($payload);
            $this->plainApiKey = $this->endpoint->rotateApiKey();
            $this->apiKeyPrefix = (string) $this->endpoint->api_key_prefix;
            session()->flash('mcp_endpoint_plain_key', $this->plainApiKey);
        } else {
            $this->endpoint->update($payload);
        }

        $this->endpoint->bindings()->delete();

        foreach (array_values($this->bindings) as $index => $binding) {
            $this->endpoint->bindings()->create([
                'kind' => $binding['kind'],
                'ref' => $binding['ref'],
                'tool_name' => ($binding['tool_name'] ?? '') !== '' ? $binding['tool_name'] : null,
                'tool_description' => ($binding['tool_description'] ?? '') !== '' ? $binding['tool_description'] : null,
                'only' => ! empty($binding['only']) ? array_values($binding['only']) : null,
                'exclude' => ! empty($binding['exclude']) ? array_values($binding['exclude']) : null,
                'enabled' => (bool) ($binding['enabled'] ?? true),
                'sort_order' => $index,
            ]);
        }

        session()->flash('success', __('neuronai-studio::flash.mcp_endpoint_saved'));

        $this->redirect(route('neuronai-studio.mcp-endpoints.edit', $this->endpoint));
    }

    /** @return array<string, mixed> */
    protected function catalogOptions(): array
    {
        $registry = app(ToolRegistry::class);
        $tools = [];
        $toolkits = [];

        foreach ($registry->all() as $entry) {
            $ref = (string) ($entry['ref'] ?? '');
            $type = (string) ($entry['type'] ?? '');

            if ($type === 'toolkit' || str_starts_with($ref, 'toolkit:')) {
                $toolkits[] = $entry;
            } elseif (str_starts_with($ref, 'tool:db:') || str_starts_with($ref, 'class:')) {
                $tools[] = $entry;
            }
        }

        return [
            'tools' => $tools,
            'toolkits' => $toolkits,
            'agents' => AgentDefinition::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']),
            'workflows' => WorkflowDefinition::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']),
        ];
    }

    /** @return array<int, array{name: string, description: string}> */
    protected function previewTools(): array
    {
        if (! $this->endpoint?->exists) {
            return [];
        }

        $this->endpoint->load('bindings');

        return array_map(static fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
        ], app(McpToolCatalog::class)->toolsFor($this->endpoint));
    }

    public function connectUrl(): string
    {
        if (! $this->endpoint?->exists) {
            return '';
        }

        $prefix = trim((string) config('neuronai-studio.mcp_endpoints.route_prefix', 'api/neuronai/mcp'), '/');

        return url($prefix.'/'.$this->endpoint->slug);
    }

    /** @return array<string, mixed> */
    public function mcpJsonSnippet(): array
    {
        $url = $this->connectUrl();
        $key = $this->plainApiKey ?: 'YOUR_API_KEY';

        return [
            'mcpServers' => [
                $this->endpoint?->slug ?: 'studio-endpoint' => [
                    'url' => $url,
                    'headers' => [
                        'Authorization' => 'Bearer '.$key,
                    ],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('neuronai-studio::livewire.mcp-endpoints.edit', [
            'catalog' => $this->catalogOptions(),
            'previewTools' => $this->previewTools(),
            'connectUrl' => $this->connectUrl(),
            'mcpJson' => $this->mcpJsonSnippet(),
            'featureEnabled' => (bool) config('neuronai-studio.mcp_endpoints.enabled', false),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => __('neuronai-studio::ui.breadcrumbs.mcp_endpoints'), 'url' => route('neuronai-studio.mcp-endpoints.index')],
                ['label' => $this->endpoint?->exists ? $this->name : __('neuronai-studio::ui.actions.new_mcp_endpoint')],
            ],
            title: $this->endpoint?->exists
                ? __('neuronai-studio::ui.breadcrumbs.edit')
                : __('neuronai-studio::ui.actions.new_mcp_endpoint'),
        ));
    }
}
