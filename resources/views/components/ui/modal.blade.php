@props([
    'title' => null,
    'maxWidth' => 'lg',
    'closeAction' => null,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        'full' => 'max-w-[min(96vw,64rem)]',
        default => 'max-w-lg',
    };
@endphp

<div {{ $attributes->merge(['class' => 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4']) }} role="dialog" aria-modal="true">
    <div class="flex max-h-[90vh] w-full {{ $maxWidthClass }} flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl" @click.stop>
        @if ($title || isset($header))
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-border px-6 py-4">
                <div class="min-w-0 flex-1">
                    @if (isset($header))
                        {{ $header }}
                    @else
                        <h2 class="text-lg font-semibold">{{ $title }}</h2>
                    @endif
                </div>
                @if ($closeAction)
                    <button type="button" class="shrink-0 text-muted-foreground hover:text-foreground" wire:click="{{ $closeAction }}" aria-label="{{ __('neuronai-studio::ui.actions.close') }}">×</button>
                @endif
            </div>
        @endif

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="shrink-0 border-t border-border px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
