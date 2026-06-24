<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\McpServers;

use ElvisLopesDigital\NeuronAIStudio\Models\McpServer;
use ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public function delete(int $id): void
    {
        McpServer::findOrFail($id)->delete();
        session()->flash('success', 'MCP server deleted.');
    }

    public function render()
    {
        $registry = app(McpRegistry::class);
        $servers = collect($registry->all());

        if ($this->filter !== '') {
            $needle = strtolower($this->filter);

            $servers = $servers->filter(function (array $entry, string $slug) use ($needle) {
                return str_contains(strtolower($slug), $needle)
                    || str_contains(strtolower($entry['label'] ?? ''), $needle)
                    || str_contains(strtolower($entry['transport'] ?? ''), $needle);
            });
        }

        return view('neuronai-studio::livewire.mcp-servers.index', [
            'servers' => $servers->sortBy('label')->all(),
        ])->layout('neuronai-studio::layouts.app', ['title' => 'MCP Servers']);
    }
}
