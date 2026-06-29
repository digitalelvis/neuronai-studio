@props(['label', 'value'])

<x-neuronai-studio::ui.card {{ $attributes }}>
    <x-neuronai-studio::ui.card-header>
        <p class="text-sm text-muted-foreground">{{ $label }}</p>
        <p class="text-3xl font-bold tracking-tight">{{ $value }}</p>
    </x-neuronai-studio::ui.card-header>
</x-neuronai-studio::ui.card>
