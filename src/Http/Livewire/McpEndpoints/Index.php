<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\McpEndpoints;

use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public function delete(int $id): void
    {
        McpEndpoint::findOrFail($id)->delete();
        session()->flash('success', __('neuronai-studio::flash.mcp_endpoint_deleted'));
    }

    public function render()
    {
        $query = McpEndpoint::query()->withCount('bindings')->orderBy('name');

        if ($this->filter !== '') {
            $needle = '%'.strtolower($this->filter).'%';
            $query->where(function ($builder) use ($needle) {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$needle]);
            });
        }

        return view('neuronai-studio::livewire.mcp-endpoints.index', [
            'endpoints' => $query->get(),
            'featureEnabled' => (bool) config('neuronai-studio.mcp_endpoints.enabled', false),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => __('neuronai-studio::ui.breadcrumbs.mcp_endpoints')]],
            title: __('neuronai-studio::ui.breadcrumbs.mcp_endpoints'),
            headerActions: view('neuronai-studio::partials.header-actions.new-mcp-endpoint')->render(),
        ));
    }
}
