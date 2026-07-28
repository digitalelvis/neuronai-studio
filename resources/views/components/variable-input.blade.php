@props([
    'wireModel' => null,
    'name' => null,
    'sensitive' => false,
    'placeholder' => '',
    'hint' => null,
])

@php
    $variables = \DigitalElvis\NeuronAIStudio\Models\Variable::query()
        ->orderBy('name')
        ->get(['id', 'name', 'type']);
@endphp

<div
    class="studio-variable-input"
    x-data="{
        open: false,
        q: '',
        wireModel: @js($wireModel),
        variables: @js($variables->map(fn ($v) => ['name' => $v->name, 'type' => $v->type])->values()),
        get filtered() {
            const needle = this.q.toLowerCase();
            if (!needle) return this.variables;
            return this.variables.filter(v => v.name.toLowerCase().includes(needle));
        },
        bind(name) {
            if (this.wireModel && typeof $wire !== 'undefined') {
                $wire.set(this.wireModel, 'var:' + name);
            }
            this.open = false;
            this.q = '';
        },
        clearBind() {
            if (this.wireModel && typeof $wire !== 'undefined') {
                $wire.set(this.wireModel, '');
            }
        },
        openCreate() {
            this.open = false;
            if (window.Livewire) {
                window.Livewire.dispatch('studio-open-create-variable');
            }
        },
        onCreated(e) {
            const detail = e.detail || {};
            const name = detail.name;
            const type = detail.type || 'credential';
            if (!name) return;
            if (!this.variables.find(v => v.name === name)) {
                this.variables = [...this.variables, { name, type }].sort((a, b) => a.name.localeCompare(b.name));
            }
            this.bind(name);
        }
    }"
    x-on:studio-variable-created.window="onCreated($event)"
    @click.outside="open = false"
>
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <x-neuronai-studio::ui.input
                type="{{ $sensitive ? 'password' : 'text' }}"
                {{ $attributes->merge(array_filter([
                    'placeholder' => $placeholder,
                    'wire:model' => $wireModel,
                    'name' => $name,
                ])) }}
            />
        </div>
        <x-neuronai-studio::ui.button type="button" variant="outline" size="sm" @click="open = !open" title="Bind Studio variable">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
        </x-neuronai-studio::ui.button>
    </div>
    <div x-show="open" x-cloak class="relative z-20 mt-1 rounded-md border border-border bg-background shadow-md">
        <div class="border-b border-border p-2">
            <x-neuronai-studio::ui.input type="text" x-model="q" placeholder="Search variables..." />
        </div>
        <ul class="max-h-48 overflow-y-auto py-1 text-sm">
            <template x-for="v in filtered" :key="v.name">
                <li>
                    <button type="button" class="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-muted" @click="bind(v.name)">
                        <code x-text="v.name"></code>
                        <span class="text-xs text-muted-foreground" x-text="v.type"></span>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-muted-foreground">No variables found</li>
        </ul>
        <div class="flex items-center justify-between gap-2 border-t border-border p-2">
            <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="clearBind()">Clear binding</button>
            <button type="button" class="text-xs font-medium text-primary hover:underline" @click="openCreate()">+ Add variable</button>
        </div>
    </div>
    @if ($hint)
        <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
