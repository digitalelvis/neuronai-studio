<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
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
            ->layout('neuronai-studio::layouts.app', ['title' => 'Run #'.$this->run->id]);
    }
}
