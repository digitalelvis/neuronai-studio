<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Tools;

use DigitalElvis\NeuronAIStudio\Codegen\ToolClassGenerator;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Show extends Component
{
    public ToolDefinition $tool;

    public function mount(ToolDefinition $tool): void
    {
        $this->tool = $tool;
    }

    public function getGeneratedPreviewProperty(): string
    {
        if ($this->tool->type === 'webhook') {
            return '';
        }

        return app(ToolClassGenerator::class)->generate([
            'class_name' => $this->tool->config['class_name'] ?? null,
            'tool_name' => $this->tool->config['tool_name'] ?? $this->tool->slug,
            'description' => $this->tool->description,
            'input_schema' => $this->tool->input_schema ?? [],
            'invoke_body' => $this->tool->config['invoke_body'] ?? "        return 'Executed';",
        ]);
    }

    public function render()
    {
        $agentsUsing = AgentDefinition::query()
            ->whereNotNull('tools')
            ->get()
            ->filter(function (AgentDefinition $agent) {
                foreach ($agent->tools ?? [] as $binding) {
                    if (($binding['ref'] ?? '') === $this->tool->bindingRef()) {
                        return true;
                    }
                }

                return false;
            });

        return view('neuronai-studio::livewire.tools.show', [
            'agentsUsing' => $agentsUsing,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Tools', 'url' => route('neuronai-studio.tools.index')],
                ['label' => $this->tool->name],
            ],
            title: $this->tool->name,
        ));
    }
}
