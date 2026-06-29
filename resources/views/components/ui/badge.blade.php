@props(['variant' => 'default'])

@php
    $variants = [
        'default' => 'border-transparent bg-primary text-primary-foreground',
        'secondary' => 'border-transparent bg-secondary text-secondary-foreground',
        'destructive' => 'border-transparent bg-destructive text-destructive-foreground',
        'outline' => 'text-foreground',
        'completed' => 'border-transparent bg-green-500/20 text-green-400',
        'failed' => 'border-transparent bg-red-500/20 text-red-400',
        'running' => 'border-transparent bg-blue-500/20 text-blue-400',
        'published' => 'border-transparent bg-primary/20 text-primary',
        'draft' => 'border-transparent bg-muted text-muted-foreground',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold ' . ($variants[$variant] ?? $variants['default'])]) }}>
    {{ $slot }}
</span>
