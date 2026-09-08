@php
    /** @var array<string, mixed> $nodes */
@endphp
<ul class="space-y-0.5 {{ ($depth ?? 0) > 0 ? 'ml-3 border-l border-border pl-2' : '' }}">
    @foreach ($nodes as $name => $value)
        @if (is_array($value))
            <li>
                <div class="flex items-center gap-1 px-2 py-1 text-xs font-medium text-muted-foreground">
                    <span>▸</span>
                    <span>{{ $name }}/</span>
                </div>
                @include('neuronai-studio::livewire.skills.partials.file-tree', ['nodes' => $value, 'selectedPath' => $selectedPath, 'depth' => ($depth ?? 0) + 1])
            </li>
        @else
            <li>
                <button
                    type="button"
                    wire:click="selectFile(@js($value))"
                    class="w-full rounded-md px-2 py-1.5 text-left text-xs {{ $selectedPath === $value ? 'bg-accent font-medium text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground' }}"
                >{{ $name }}</button>
            </li>
        @endif
    @endforeach
</ul>
