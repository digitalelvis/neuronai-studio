<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use Livewire\Component;

class Playground extends Component
{
    public AgentDefinition $agent;

    public function mount(AgentDefinition $agent): void
    {
        $this->agent = $agent->load('mcpBindings');
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.playground', [
            'mcpToolCount' => $this->estimateMcpToolCount(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Playground — '.$this->agent->name]);
    }

    protected function estimateMcpToolCount(): int
    {
        if ($this->agent->mcpBindings->isEmpty()) {
            return 0;
        }

        try {
            return count(app(McpToolResolver::class)->toolsForAgent($this->agent));
        } catch (\Throwable) {
            return $this->agent->mcpBindings->count();
        }
    }
}
