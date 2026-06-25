<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class RunDetail extends Component
{
    public WorkflowRun $run;

    public function mount(WorkflowRun $run): void
    {
        $this->run = $run->load(['workflow', 'steps']);
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.run-detail')
            ->layout('neuronai-studio::layouts.app', StudioLayout::params(
                breadcrumbs: [
                    ['label' => 'Workflows', 'url' => route('neuronai-studio.workflows.index')],
                    ['label' => $this->run->workflow?->name ?? 'Workflow', 'url' => $this->run->workflow ? route('neuronai-studio.workflows.edit', $this->run->workflow) : null],
                    ['label' => 'Runs', 'url' => $this->run->workflow ? route('neuronai-studio.workflows.runs', $this->run->workflow) : null],
                    ['label' => 'Run #'.$this->run->id],
                ],
                title: 'Run #'.$this->run->id,
                contentFlush: true,
            ));
    }
}
