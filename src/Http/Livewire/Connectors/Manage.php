<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use Livewire\Attributes\On;
use Livewire\Component;

class Manage extends Component
{
    public string $filter = '';

    public string $type = 'all';

    public function openDetail(string $ref): void
    {
        $this->dispatch('connector-open-detail', connectorRef: $ref)->to(Detail::class);
    }

    public function browseCatalog(): void
    {
        $this->dispatch('connector-switch-view', view: 'catalog');
    }

    #[On('connector-catalog-refresh')]
    public function refreshList(): void
    {
        // triggers re-render
    }

    public function render()
    {
        $catalog = app(ConnectorCatalog::class);
        $entries = $catalog->filter($catalog->installedEntries(), $this->filter, $this->type);

        return view('neuronai-studio::livewire.connectors.manage', [
            'entries' => $entries,
            'types' => app(ConnectorCatalog::class)->installedFilterTypes(),
        ]);
    }
}
