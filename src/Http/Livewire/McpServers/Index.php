<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\McpServers;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public function delete(int $id): void
    {
        McpServer::findOrFail($id)->delete();
        session()->flash('success', __('neuronai-studio::flash.mcp_deleted'));
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
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => __('neuronai-studio::ui.breadcrumbs.mcp_servers')]],
            title: __('neuronai-studio::ui.breadcrumbs.mcp_servers'),
            headerActions: view('neuronai-studio::partials.header-actions.new-mcp')->render(),
        ));
    }
}
