<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Concerns\EmbeddableConnectorForm;
use DigitalElvis\NeuronAIStudio\McpServer\McpJsonImporter;
use Livewire\Component;
use Throwable;

class ImportMcpJson extends Component
{
    use EmbeddableConnectorForm;

    public string $json = '';

    public string $error = '';

    public function import(McpJsonImporter $importer): void
    {
        $this->error = '';

        if (trim($this->json) === '') {
            $this->error = __('neuronai-studio::connectors.mcp_json_empty');

            return;
        }

        try {
            $servers = $importer->import($this->json);
            $this->json = '';

            session()->flash('success', __('neuronai-studio::connectors.mcp_json_success', ['count' => count($servers)]));

            if ($this->embedded) {
                $this->dispatch('connector-saved', connectorRef: 'mcp:'.($servers[0]->slug ?? ''));
                $this->dispatch('connector-catalog-refresh');

                return;
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('neuronai-studio::livewire.connectors.import-mcp-json');
    }
}
