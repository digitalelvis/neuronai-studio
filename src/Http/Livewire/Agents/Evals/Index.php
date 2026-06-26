<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Index extends Component
{
    public AgentDefinition $agent;

    public function mount(AgentDefinition $agent): void
    {
        $this->agent = $agent;
    }

    public function delete(int $suiteId): void
    {
        $this->agent->evalSuites()->whereKey($suiteId)->delete();
        session()->flash('success', 'Eval suite deleted.');
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.evals.index', [
            'suites' => $this->agent->evalSuites()->with('judgeAgent')->latest()->get(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->agent->name, 'url' => route('neuronai-studio.agents.edit', $this->agent)],
                ['label' => 'Evals'],
            ],
            title: 'Evals — '.$this->agent->name,
            headerActions: view('neuronai-studio::partials.header-actions.new-eval-suite', ['agent' => $this->agent])->render(),
        ));
    }
}
