<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $registryCount = count(app(ToolRegistry::class)->all());

        return view('neuronai-studio::livewire.dashboard', [
            'agentCount' => AgentDefinition::count(),
            'workflowCount' => WorkflowDefinition::count(),
            'toolCount' => max(ToolDefinition::count(), $registryCount),
            'mcpServerCount' => count(app(\DigitalElvis\NeuronAIStudio\Registry\McpRegistry::class)->all()),
            'recentTraces' => StudioRun::with('thread.entity')->latest()->limit(10)->get(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Dashboard']],
            title: 'Dashboard',
        ));
    }
}
