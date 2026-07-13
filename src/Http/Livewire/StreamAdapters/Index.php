<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\StreamAdapters;

use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $registry = app(StreamAdapterRegistry::class);

        return view('neuronai-studio::livewire.stream-adapters.index', [
            'available' => $registry->available(),
            'roadmap' => $registry->roadmap(),
            'enabled' => config('neuronai-studio.stream_adapters.enabled', true),
            'routePrefix' => config('neuronai-studio.stream_adapters.route_prefix', 'api/neuronai'),
            'middleware' => config('neuronai-studio.stream_adapters.middleware', ['api']),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Stream Adapters']],
            title: 'Stream Adapters',
        ));
    }
}
