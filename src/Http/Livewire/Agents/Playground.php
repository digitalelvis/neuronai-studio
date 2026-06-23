<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use Livewire\Component;

class Playground extends Component
{
    public AgentDefinition $agent;

    public string $message = '';

    public string $response = '';

    public bool $loading = false;

    public function mount(AgentDefinition $agent): void
    {
        $this->agent = $agent;
    }

    public function send(AgentRunner $runner): void
    {
        $this->validate(['message' => 'required|string']);

        $this->loading = true;

        try {
            $this->response = $runner->run($this->agent, $this->message);
        } catch (\Throwable $exception) {
            $this->response = 'Error: '.$exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.playground')
            ->layout('neuronai-studio::layouts.app', ['title' => 'Playground — '.$this->agent->name]);
    }
}
