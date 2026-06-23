<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Registry\ToolRegistry;
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

    public function updatedProvider(string $value): void
    {
        $models = config('neuronai-studio.providers.'.$value.'.models', []);
        $this->model = $models[0] ?? $this->model;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|string',
            'model' => 'required|string',
            'instructions' => 'nullable|string',
            'selectedToolRefs' => 'array',
            'selectedToolRefs.*' => 'string',
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

    public function render()
    {
        $registry = app(ToolRegistry::class);
        $catalog = collect($registry->all())->groupBy('category');

        return view('neuronai-studio::livewire.agents.edit', [
            'providers' => app(ProviderRegistry::class)->labels(),
            'models' => config('neuronai-studio.providers.'.$this->provider.'.models', []),
            'toolCatalog' => $catalog,
            'toolCategories' => [
                'builtin' => 'Built-in Toolkits',
                'app' => 'App Classes',
                'studio' => 'Studio Tools',
                'mcp' => 'MCP Servers',
            ],
        ])->layout('neuronai-studio::layouts.app', [
            'title' => $this->agent?->exists ? 'Edit Agent' : 'Create Agent',
        ]);
    }
}
