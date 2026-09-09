<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use Livewire\Attributes\On;
use Livewire\Component;

class Catalog extends Component
{
    public string $filter = '';

    public string $type = 'all';

    public function openDetail(string $ref): void
    {
        $this->dispatch('connector-open-detail', connectorRef: $ref)->to(Detail::class);
    }

    public function installPlugin(string $slug): void
    {
        $this->dispatch('connector-install-plugin', slug: $slug);
    }

    public function createDataSource(string $driver): void
    {
        $this->dispatch('connector-open-create', type: 'rag', vectorStoreDriver: $driver);
    }

    #[On('connector-catalog-refresh')]
    public function refreshCatalog(): void
    {
        // triggers re-render
    }

    public function render()
    {
        $catalog = app(ConnectorCatalog::class);
        $entries = $catalog->filter($catalog->catalogEntries(), $this->filter, $this->type);

        return view('neuronai-studio::livewire.connectors.catalog', [
            'entries' => $entries,
            'types' => $catalog->catalogFilterTypes(),
        ]);
    }
}
