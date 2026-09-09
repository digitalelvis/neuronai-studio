@props(['entry'])

@php
    $type = (string) ($entry['type'] ?? '');
    $initial = strtoupper(substr((string) ($entry['name'] ?? '?'), 0, 1));
    $icon = $entry['icon'] ?? null;
@endphp

<button
    type="button"
    wire:click="openDetail('{{ $entry['ref'] }}')"
    class="group flex w-full items-start gap-3 rounded-xl border border-border bg-card p-4 text-left transition hover:border-primary/40 hover:shadow-sm"
    wire:key="connector-card-{{ $entry['ref'] }}"
>
    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted text-sm font-semibold text-muted-foreground">
        @if (! empty($icon))
            <img src="{{ $icon }}" alt="" class="h-full w-full object-cover" />
        @else
            {{ $initial }}
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
            <h3 class="font-semibold leading-snug group-hover:text-primary">{{ $entry['name'] }}</h3>
            @if (($entry['installed'] ?? false) && $type === 'plugin')
                <x-neuronai-studio::ui.badge variant="published">{{ __('neuronai-studio::connectors.installed') }}</x-neuronai-studio::ui.badge>
            @endif
        </div>
        @if (! empty($entry['description']))
            <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ $entry['description'] }}</p>
        @endif
        <p class="mt-2 text-xs uppercase tracking-wide text-muted-foreground">{{ __('neuronai-studio::connectors.types.'.$type) }}</p>
    </div>
    @if ($type === 'plugin' && ! ($entry['installed'] ?? false))
        <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border text-lg leading-none text-muted-foreground group-hover:border-primary group-hover:text-primary"
            wire:click.stop="installPlugin('{{ $entry['slug'] }}')"
            title="{{ __('neuronai-studio::plugins.add') }}"
        >+</span>
    @elseif ($type === 'data_source')
        <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border text-lg leading-none text-muted-foreground group-hover:border-primary group-hover:text-primary"
            wire:click.stop="createDataSource('{{ $entry['slug'] }}')"
            title="{{ __('neuronai-studio::connectors.create_data_source') }}"
        >+</span>
    @endif
</button>
