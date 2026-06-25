<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Traces extends Component
{
    public WorkflowDefinition $workflow;

    public function mount(WorkflowDefinition $workflow): void
    {
        $this->workflow = $workflow;
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.traces', [
            'traces' => $this->workflow->traces()->latest()->get(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Workflows', 'url' => route('neuronai-studio.workflows.index')],
                ['label' => $this->workflow->name, 'url' => route('neuronai-studio.workflows.edit', $this->workflow)],
                ['label' => 'Traces'],
            ],
            title: 'Traces — '.$this->workflow->name,
        ));
    }
}
