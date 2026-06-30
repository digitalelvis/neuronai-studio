<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Tools;

use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class RegistryShow extends Component
{
    public string $ref = '';

    /** @var array<string, mixed> */
    public array $entry = [];

    /** @var array<string, mixed> */
    public array $config = [];

    public function mount(): void
    {
        $this->ref = (string) request('ref', '');

        if ($this->ref === '') {
            abort(404);
        }

        $registry = app(ToolRegistry::class);
        $this->entry = $registry->find($this->ref) ?? abort(404);
        $this->config = $registry->configFor($this->ref);
    }

    public function render()
    {
        $categoryLabels = [
            'builtin' => 'Built-in Toolkit',
            'app' => 'App Class',
            'studio' => 'Studio Tool',
            'mcp' => 'MCP Server',
        ];

        return view('neuronai-studio::livewire.tools.registry-show', [
            'categoryLabel' => $categoryLabels[$this->entry['category'] ?? ''] ?? ($this->entry['category'] ?? ''),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Tools', 'url' => route('neuronai-studio.tools.index')],
                ['label' => $this->entry['label'] ?? 'Tool Details'],
            ],
            title: $this->entry['label'] ?? 'Tool Details',
        ));
    }
}
