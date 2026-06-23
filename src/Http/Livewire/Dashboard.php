<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('neuronai-studio::livewire.dashboard', [
            'agentCount' => AgentDefinition::count(),
            'workflowCount' => WorkflowDefinition::count(),
            'recentRuns' => WorkflowRun::with('workflow')->latest()->limit(10)->get(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Dashboard']);
    }
}
