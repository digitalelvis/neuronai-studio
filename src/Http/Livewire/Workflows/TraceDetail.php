<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class TraceDetail extends Component
{
    public StudioRun $trace;

    public function mount(StudioRun $run): void
    {
        $this->trace = $run->load(['thread.entity', 'traces.spans']);
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.trace-detail')
            ->layout('neuronai-studio::layouts.app', StudioLayout::params(
                breadcrumbs: [
                    ['label' => __('neuronai-studio::ui.breadcrumbs.workflows'), 'url' => route('neuronai-studio.workflows.index')],
                    ['label' => $this->trace->workflow?->name ?? 'Workflow', 'url' => $this->trace->workflow ? route('neuronai-studio.workflows.edit', $this->trace->workflow) : null],
                    ['label' => __('neuronai-studio::ui.breadcrumbs.traces'), 'url' => $this->trace->workflow ? route('neuronai-studio.workflows.traces', $this->trace->workflow) : null],
                    ['label' => 'Trace #'.$this->trace->id],
                ],
                title: 'Trace #'.$this->trace->id,
                contentFlush: true,
            ));
    }
}
