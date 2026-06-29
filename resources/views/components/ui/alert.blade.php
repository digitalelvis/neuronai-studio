@props(['variant' => 'default'])

@php
    $variants = [
        'default' => 'border-border bg-card text-foreground',
        'success' => 'border-green-500/30 bg-green-500/10 text-green-400',
        'error' => 'border-destructive/30 bg-destructive/10 text-red-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm ' . ($variants[$variant] ?? $variants['default'])]) }} role="alert">
    {{ $slot }}
</div>
