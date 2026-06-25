<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowTrace;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class TraceDetail extends Component
{
    public WorkflowTrace $trace;

    public function mount(WorkflowTrace $trace): void
    {
        $this->trace = $trace->load(['workflow', 'steps']);
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.trace-detail')
            ->layout('neuronai-studio::layouts.app', StudioLayout::params(
                breadcrumbs: [
                    ['label' => 'Workflows', 'url' => route('neuronai-studio.workflows.index')],
                    ['label' => $this->trace->workflow?->name ?? 'Workflow', 'url' => $this->trace->workflow ? route('neuronai-studio.workflows.edit', $this->trace->workflow) : null],
                    ['label' => 'Traces', 'url' => $this->trace->workflow ? route('neuronai-studio.workflows.traces', $this->trace->workflow) : null],
                    ['label' => 'Trace #'.$this->trace->id],
                ],
                title: 'Trace #'.$this->trace->id,
                contentFlush: true,
            ));
    }
}
