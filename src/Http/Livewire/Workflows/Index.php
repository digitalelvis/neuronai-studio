<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use Livewire\Component;

class Index extends Component
{
    public function delete(int $workflowId): void
    {
        WorkflowDefinition::findOrFail($workflowId)->delete();
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.index', [
            'workflows' => WorkflowDefinition::latest()->get(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Workflows']);
    }
}
