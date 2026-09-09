<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Concerns;

trait EmbeddableConnectorForm
{
    public bool $embedded = false;

    protected function finishConnectorSave(?string $ref = null, ?string $message = null): void
    {
        if ($message !== null) {
            session()->flash('success', $message);
        }

        if ($this->embedded) {
            $this->dispatch('connector-saved', connectorRef: $ref);

            return;
        }
    }

    protected function shouldRedirectAfterSave(): bool
    {
        return ! $this->embedded;
    }
}
