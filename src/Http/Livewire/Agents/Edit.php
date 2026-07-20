<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Agents;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentMcpServer;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;

class Edit extends Component
{
    public ?AgentDefinition $agent = null;

    public string $name = '';

    public string $description = '';

    public string $provider = '';

    public string $model = '';

    public string $instructions = '';

    /** @var array<int, string> */
    public array $selectedToolRefs = [];

    /** @var array<string, array{only: string, exclude: string}> */
    public array $toolAdvanced = [];

    /** @var array<int, string> */
    public array $selectedMcpSlugs = [];

    /** @var array<string, array{only: string, exclude: string}> */
    public array $mcpAdvanced = [];

    public ?int $tool_max_runs = null;

    public ?bool $parallel_tool_calls = null;

    public ?int $memory_context_window = null;

    public ?string $memory_driver = null;

    public ?bool $memory_summarization_enabled = null;

    public $memory_summarization_threshold = null;

    public function mount(?AgentDefinition $agent = null): void
    {
        $this->agent = $agent;
        $this->provider = config('neuronai-studio.default_provider', 'openai');

        if ($agent?->exists) {
            $this->name = $agent->name;
            $this->description = (string) $agent->description;
            $this->provider = $agent->provider;
            $this->model = $agent->model;
            $this->instructions = (string) $agent->instructions;
            $this->tool_max_runs = $agent->tool_max_runs;
            $this->parallel_tool_calls = $agent->parallel_tool_calls;
            $this->hydrateMemoryFromConfig($agent->memory_config);
            $this->loadToolsFromAgent($agent->tools ?? []);
            $this->loadMcpFromAgent($agent);
        } else {
            $models = config('neuronai-studio.providers.'.$this->provider.'.models', []);
            $this->model = $models[0] ?? config('neuronai-studio.default_model', 'gpt-4o-mini');
        }
    }

    /** @param  array<string, mixed>|null  $config */
    protected function hydrateMemoryFromConfig(?array $config): void
    {
        if (! is_array($config) || $config === []) {
            return;
        }

        $memory = \DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig::fromArray($config);
        $this->memory_context_window = $memory->contextWindow();
        $this->memory_driver = $memory->driver();
        $this->memory_summarization_enabled = $memory->summarizationEnabled();
        $this->memory_summarization_threshold = $memory->summarizationThreshold();
    }

    /** @param  array<int, array<string, mixed>>  $tools */
    protected function loadToolsFromAgent(array $tools): void
    {
        foreach ($tools as $tool) {
            if (empty($tool['ref'])) {
                continue;
            }

            $this->selectedToolRefs[] = $tool['ref'];
            $this->toolAdvanced[$tool['ref']] = [
                'only' => implode(', ', $tool['only'] ?? []),
                'exclude' => implode(', ', $tool['exclude'] ?? []),
            ];
        }
    }

    protected function loadMcpFromAgent(AgentDefinition $agent): void
    {
        $agent->loadMissing('mcpBindings');

        foreach ($agent->mcpBindings as $binding) {
            $this->selectedMcpSlugs[] = $binding->mcp_server_slug;
            $this->mcpAdvanced[$binding->mcp_server_slug] = [
                'only' => (string) $binding->only_tools,
                'exclude' => implode(', ', $binding->exclude_tools ?? []),
            ];
        }
    }

    public function updatedProvider(string $value): void
    {
        $models = config('neuronai-studio.providers.'.$value.'.models', []);
        $this->model = $models[0] ?? $this->model;
    }

    public function save(): void
    {
        $this->persistAgent();
    }

    /** @param  array<string, mixed>  $payload */
    public function saveFromReact(array $payload): void
    {
        $this->name = (string) ($payload['name'] ?? '');
        $this->description = (string) ($payload['description'] ?? '');
        $this->provider = (string) ($payload['provider'] ?? $this->provider);
        $this->model = (string) ($payload['model'] ?? $this->model);
        $this->instructions = (string) ($payload['instructions'] ?? '');
        $this->selectedToolRefs = $payload['selectedToolRefs'] ?? [];
        $this->toolAdvanced = $payload['toolAdvanced'] ?? [];
        $this->selectedMcpSlugs = $payload['selectedMcpSlugs'] ?? [];
        $this->mcpAdvanced = $payload['mcpAdvanced'] ?? [];
        $this->tool_max_runs = isset($payload['tool_max_runs']) && $payload['tool_max_runs'] !== '' && $payload['tool_max_runs'] !== null
            ? (int) $payload['tool_max_runs']
            : null;
        $this->parallel_tool_calls = array_key_exists('parallel_tool_calls', $payload)
            ? (isset($payload['parallel_tool_calls']) ? (bool) $payload['parallel_tool_calls'] : null)
            : $this->parallel_tool_calls;

        $this->memory_context_window = isset($payload['memory_context_window']) && $payload['memory_context_window'] !== '' && $payload['memory_context_window'] !== null
            ? (int) $payload['memory_context_window']
            : null;
        $this->memory_driver = isset($payload['memory_driver']) && $payload['memory_driver'] !== ''
            ? (string) $payload['memory_driver']
            : null;
        $this->memory_summarization_enabled = array_key_exists('memory_summarization_enabled', $payload)
            ? (isset($payload['memory_summarization_enabled']) ? (bool) $payload['memory_summarization_enabled'] : null)
            : $this->memory_summarization_enabled;
        $this->memory_summarization_threshold = isset($payload['memory_summarization_threshold']) && $payload['memory_summarization_threshold'] !== '' && $payload['memory_summarization_threshold'] !== null
            ? (float) $payload['memory_summarization_threshold']
            : null;

        $this->persistAgent();
    }

    protected function persistAgent(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|string',
            'model' => 'required|string',
            'instructions' => 'nullable|string',
            'tool_max_runs' => 'nullable|integer|min:1',
            'parallel_tool_calls' => 'nullable|boolean',
            'memory_context_window' => 'nullable|integer|min:1',
            'memory_driver' => 'nullable|string|in:eloquent,in_memory',
            'memory_summarization_enabled' => 'nullable|boolean',
            'memory_summarization_threshold' => 'nullable|numeric|gt:0|lte:1',
            'selectedToolRefs' => 'array',
            'selectedToolRefs.*' => 'string',
            'selectedMcpSlugs' => 'array',
            'selectedMcpSlugs.*' => 'string',
        ]);

        $memoryConfig = $this->buildMemoryConfigPayload();

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'instructions' => $validated['instructions'] ?? null,
            'slug' => Str::slug($this->name),
            'tools' => $this->buildToolsPayload(),
            'tool_max_runs' => $this->tool_max_runs,
            'parallel_tool_calls' => $this->parallel_tool_calls,
            'memory_config' => $memoryConfig,
        ];

        if ($this->agent?->exists) {
            $this->agent->update($payload);
        } else {
            $this->agent = AgentDefinition::create($payload);
        }

        $this->syncMcpBindings($this->agent);

        session()->flash('success', 'Agent saved successfully.');

        $this->redirect(route('neuronai-studio.agents.index'));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildMemoryConfigPayload(): ?array
    {
        $raw = [];

        if ($this->memory_context_window !== null) {
            $raw['context_window'] = $this->memory_context_window;
        }
        if ($this->memory_driver !== null && $this->memory_driver !== '') {
            $raw['driver'] = $this->memory_driver;
        }
        if ($this->memory_summarization_enabled !== null) {
            $raw['summarization_enabled'] = $this->memory_summarization_enabled;
        }
        if ($this->memory_summarization_threshold !== null && $this->memory_summarization_threshold !== '') {
            $raw['summarization_threshold'] = (float) $this->memory_summarization_threshold;
        }

        if ($raw === []) {
            return null;
        }

        return \DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig::fromArray($raw)->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    protected function buildToolsPayload(): array
    {
        $tools = [];

        foreach ($this->selectedToolRefs as $ref) {
            $binding = ['ref' => $ref, 'config' => []];

            $advanced = $this->toolAdvanced[$ref] ?? [];

            if (! empty($advanced['only'])) {
                $binding['only'] = array_values(array_filter(array_map('trim', explode(',', $advanced['only']))));
            }

            if (! empty($advanced['exclude'])) {
                $binding['exclude'] = array_values(array_filter(array_map('trim', explode(',', $advanced['exclude']))));
            }

            $tools[] = $binding;
        }

        return $tools;
    }

    protected function syncMcpBindings(AgentDefinition $agent): void
    {
        $registry = app(McpRegistry::class);
        $agent->mcpBindings()->delete();

        foreach ($this->selectedMcpSlugs as $slug) {
            if ($registry->find($slug) === null) {
                continue;
            }

            $advanced = $this->mcpAdvanced[$slug] ?? [];
            $entry = $registry->find($slug);

            AgentMcpServer::create([
                'agent_definition_id' => $agent->id,
                'mcp_server_slug' => $slug,
                'mcp_server_id' => $entry['source'] === 'database' ? ($entry['id'] ?? null) : null,
                'only_tools' => ($advanced['only'] ?? '') !== '' ? $advanced['only'] : null,
                'exclude_tools' => ($advanced['exclude'] ?? '') !== ''
                    ? array_values(array_filter(array_map('trim', explode(',', $advanced['exclude']))))
                    : null,
            ]);
        }
    }

    public function render()
    {
        $registry = app(ToolRegistry::class);
        $catalog = collect($registry->all())->groupBy('category');

        $toolList = $catalog->flatten(1)->map(fn (array $tool) => [
            'ref' => $tool['ref'],
            'label' => $tool['label'],
            'description' => $tool['description'] ?? '',
            'type' => $tool['type'] ?? '',
            'category' => $tool['category'] ?? '',
        ])->values()->all();

        return view('neuronai-studio::livewire.agents.edit', [
            'providers' => app(ProviderRegistry::class)->labels(),
            'models' => config('neuronai-studio.providers.'.$this->provider.'.models', []),
            'toolList' => $toolList,
            'mcpServers' => app(McpRegistry::class)->labels(includeDisabled: false),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->agent?->exists ? $this->name : 'New Agent'],
            ],
            title: $this->agent?->exists ? 'Edit Agent' : 'Create Agent',
            contentFlush: true,
        ));
    }
}
