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
            $this->loadToolsFromAgent($agent->tools ?? []);
            $this->loadMcpFromAgent($agent);
        } else {
            $models = config('neuronai-studio.providers.'.$this->provider.'.models', []);
            $this->model = $models[0] ?? config('neuronai-studio.default_model', 'gpt-4o-mini');
        }
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
            'selectedToolRefs' => 'array',
            'selectedToolRefs.*' => 'string',
            'selectedMcpSlugs' => 'array',
            'selectedMcpSlugs.*' => 'string',
        ]);

        $payload = array_merge($validated, [
            'slug' => Str::slug($this->name),
            'tools' => $this->buildToolsPayload(),
        ]);

        unset($payload['selectedToolRefs']);

        if ($this->agent?->exists) {
            $this->agent->update($payload);
        } else {
            $this->agent = AgentDefinition::create($payload);
        }

        $this->syncMcpBindings($this->agent);

        session()->flash('success', 'Agent saved successfully.');

        $this->redirect(route('neuronai-studio.agents.index'));
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
