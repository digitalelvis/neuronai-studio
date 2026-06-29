<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Agents\Evals;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\EvalSuite;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Runs extends Component
{
    public AgentDefinition $agent;

    public EvalSuite $suite;

    public function mount(AgentDefinition $agent, EvalSuite $suite): void
    {
        abort_unless($suite->agent_definition_id === $agent->id, 404);

        $this->agent = $agent;
        $this->suite = $suite;
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.evals.runs', [
            'runs' => $this->suite->runs()->latest()->get(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->agent->name, 'url' => route('neuronai-studio.agents.edit', $this->agent)],
                ['label' => 'Evals', 'url' => route('neuronai-studio.agents.evals.index', $this->agent)],
                ['label' => $this->suite->name, 'url' => route('neuronai-studio.agents.evals.edit', ['agent' => $this->agent, 'suite' => $this->suite])],
                ['label' => 'Runs'],
            ],
            title: 'Runs — '.$this->suite->name,
        ));
    }
}
