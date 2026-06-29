@props([
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <p class="text-sm font-medium text-muted-foreground">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-xs text-muted-foreground/70">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
