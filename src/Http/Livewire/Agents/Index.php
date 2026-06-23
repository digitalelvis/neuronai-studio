<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use Livewire\Component;

class Index extends Component
{
    public function delete(int $agentId): void
    {
        AgentDefinition::findOrFail($agentId)->delete();
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.index', [
            'agents' => AgentDefinition::latest()->get(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Agents']);
    }
}
