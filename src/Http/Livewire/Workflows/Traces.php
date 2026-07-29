<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
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
                ['label' => __('neuronai-studio::ui.breadcrumbs.workflows'), 'url' => route('neuronai-studio.workflows.index')],
                ['label' => $this->workflow->name, 'url' => route('neuronai-studio.workflows.edit', $this->workflow)],
                ['label' => __('neuronai-studio::ui.breadcrumbs.traces')],
            ],
            title: 'Traces — '.$this->workflow->name,
        ));
    }
}
