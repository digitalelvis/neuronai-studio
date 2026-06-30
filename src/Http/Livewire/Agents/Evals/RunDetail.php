<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Agents\Evals;

use DigitalElvis\NeuronAIStudio\Models\EvalRun;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class RunDetail extends Component
{
    public EvalRun $run;

    public function mount(EvalRun $run): void
    {
        $this->run = $run->load(['suite', 'agentDefinition', 'judgeAgent', 'items']);
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.evals.run-detail')->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->run->agentDefinition?->name ?? 'Agent', 'url' => $this->run->agentDefinition ? route('neuronai-studio.agents.edit', $this->run->agentDefinition) : null],
                ['label' => 'Evals', 'url' => $this->run->agentDefinition ? route('neuronai-studio.agents.evals.index', $this->run->agentDefinition) : null],
                ['label' => $this->run->suite?->name ?? 'Suite', 'url' => ($this->run->agentDefinition && $this->run->suite) ? route('neuronai-studio.agents.evals.runs', ['agent' => $this->run->agentDefinition, 'suite' => $this->run->suite]) : null],
                ['label' => 'Run #'.$this->run->id],
            ],
            title: 'Eval Run #'.$this->run->id,
        ));
    }
}
