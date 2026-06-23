<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use Livewire\Component;

class Runs extends Component
{
    public WorkflowDefinition $workflow;

    public function mount(WorkflowDefinition $workflow): void
    {
        $this->workflow = $workflow;
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.runs', [
            'runs' => $this->workflow->runs()->latest()->get(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Runs — '.$this->workflow->name]);
    }
}
