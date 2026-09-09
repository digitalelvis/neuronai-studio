<div>
    <div class="mb-4">
        <x-neuronai-studio::ui.input type="search" wire:model.live.debounce.300ms="filter" placeholder="{{ __('neuronai-studio::connectors.search_placeholder') }}" />
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($types as $typeKey)
            <button
                type="button"
                wire:click="$set('type', '{{ $typeKey }}')"
                class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium {{ $type === $typeKey ? 'bg-primary text-primary-foreground' : 'border border-input bg-background hover:bg-accent' }}"
            >{{ __('neuronai-studio::connectors.types.'.$typeKey) }}</button>
        @endforeach
    </div>

    @if ($entries === [])
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.empty-state :title="__('neuronai-studio::connectors.empty_installed')" />
        </x-neuronai-studio::ui.card>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($entries as $entry)
                @include('neuronai-studio::livewire.connectors.partials.card', ['entry' => $entry])
            @endforeach
        </div>
    @endif
</div>
