<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
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
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->agent->name, 'url' => route('neuronai-studio.agents.edit', $this->agent)],
                ['label' => 'Playground'],
            ],
            title: 'Playground — '.$this->agent->name,
            contentFlush: true,
        ));
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
