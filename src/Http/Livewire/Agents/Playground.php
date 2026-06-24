<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use Livewire\Component;

class Playground extends Component
{
    public AgentDefinition $agent;

    public string $message = '';

    public string $response = '';

    /** @var array<int, array{name: string, inputs: array, result: string|null, type: string}> */
    public array $toolEvents = [];

    public bool $loading = false;

    public function mount(AgentDefinition $agent): void
    {
        $this->agent = $agent->load('mcpBindings');
    }

    public function send(AgentRunner $runner): void
    {
        $this->validate(['message' => 'required|string']);

        $this->loading = true;

        try {
            $result = $runner->run($this->agent, $this->message);
            $this->response = $result->content;
            $this->toolEvents = $result->toolEvents;
        } catch (\Throwable $exception) {
            $this->response = 'Error: '.$exception->getMessage();
            $this->toolEvents = [];
        } finally {
            $this->loading = false;
        }
    }

    public function clear(): void
    {
        $this->message = '';
        $this->response = '';
        $this->toolEvents = [];
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
