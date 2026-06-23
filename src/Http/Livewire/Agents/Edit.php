<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
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
        } else {
            $models = config('neuronai-studio.providers.'.$this->provider.'.models', []);
            $this->model = $models[0] ?? config('neuronai-studio.default_model', 'gpt-4o-mini');
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
        ]);

        $payload = array_merge($validated, [
            'slug' => Str::slug($this->name),
        ]);

        if ($this->agent?->exists) {
            $this->agent->update($payload);
        } else {
            $this->agent = AgentDefinition::create($payload);
        }

        session()->flash('success', 'Agent saved successfully.');

        $this->redirect(route('neuronai-studio.agents.index'));
    }

    public function render()
    {
        $providers = app(ProviderRegistry::class)->labels();

        return view('neuronai-studio::livewire.agents.edit', [
            'providers' => $providers,
            'models' => config('neuronai-studio.providers.'.$this->provider.'.models', []),
        ])->layout('neuronai-studio::layouts.app', [
            'title' => $this->agent?->exists ? 'Edit Agent' : 'Create Agent',
        ]);
    }
}
