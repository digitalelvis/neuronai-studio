<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\ToolDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use ElvisLopesDigital\NeuronAIStudio\Registry\ToolRegistry;
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
            'mcpServerCount' => count(app(\ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry::class)->all()),
            'recentRuns' => WorkflowRun::with('workflow')->latest()->limit(10)->get(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Dashboard']);
    }
}
