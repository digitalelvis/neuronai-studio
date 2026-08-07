<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Concerns;

trait DispatchesStudioToast
{
    /**
     * Flash a session message (for inline alert) and dispatch a browser toast.
     */
    protected function flashToast(string $variant, string $message): void
    {
        $flashKey = match ($variant) {
            'success' => 'success',
            'error' => 'error',
            default => null,
        };

        if ($flashKey !== null) {
            session()->flash($flashKey, $message);
        }

        $this->dispatchStudioToast($variant, $message);
    }

    /**
     * Dispatch a browser toast without writing session flash.
     */
    protected function toastOnly(string $variant, string $message): void
    {
        $this->dispatchStudioToast($variant, $message);
    }

    protected function dispatchStudioToast(string $variant, string $message): void
    {
        $this->dispatch('studio-toast', variant: $variant, message: $message);
    }
}
