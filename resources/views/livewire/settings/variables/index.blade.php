<x-neuronai-studio::ui.page>
    <div class="mb-4 flex justify-end">
        <x-neuronai-studio::ui.button type="button" wire:click="openCreate">+ Add New</x-neuronai-studio::ui.button>
    </div>

    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="pt-4">
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>Filter</x-neuronai-studio::ui.label>
                <x-neuronai-studio::ui.input type="text" wire:model.live="filter" placeholder="Search by name or type..." />
            </x-neuronai-studio::ui.form-group>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        @if ($variables->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No global variables yet">
                <p class="mb-3 text-sm text-muted-foreground">Create Credential or Generic variables to bind into agents, MCP, and knowledge bases without editing .env.</p>
                <x-neuronai-studio::ui.button type="button" wire:click="openCreate">+ Add New</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Type</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Value</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($variables as $variable)
                        <x-neuronai-studio::ui.table-row wire:key="var-{{ $variable->id }}">
                            <x-neuronai-studio::ui.table-cell>
                                <code class="text-sm font-medium">{{ $variable->name }}</code>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                @if ($variable->isCredential())
                                    <x-neuronai-studio::ui.badge variant="secondary">Credential</x-neuronai-studio::ui.badge>
                                @else
                                    <x-neuronai-studio::ui.badge variant="published">Generic</x-neuronai-studio::ui.badge>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <span class="font-mono text-sm text-muted-foreground">{{ $variable->display_value }}</span>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" type="button" wire:click="openEdit({{ $variable->id }})">Edit</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" type="button" wire:click="delete({{ $variable->id }})" wire:confirm="Delete this variable? References will fail at runtime." class="text-destructive">Delete</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
            <div class="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-lg" role="dialog" aria-modal="true">
                <h2 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Variable' : 'Create Variable' }}</h2>

                <div class="space-y-4">
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Type</x-neuronai-studio::ui.label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center rounded-md px-3 text-xs font-medium {{ $formType === 'credential' ? 'bg-primary text-primary-foreground' : 'border border-input bg-background hover:bg-accent' }}"
                                wire:click="$set('formType', 'credential')"
                            >Credential</button>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center rounded-md px-3 text-xs font-medium {{ $formType === 'generic' ? 'bg-primary text-primary-foreground' : 'border border-input bg-background hover:bg-accent' }}"
                                wire:click="$set('formType', 'generic')"
                            >Generic</button>
                        </div>
                    </x-neuronai-studio::ui.form-group>

                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Name</x-neuronai-studio::ui.label>
                        @if ($editingId !== null)
                            <x-neuronai-studio::ui.input type="text" wire:model="formName" placeholder="OPENAI_PROD" disabled />
                        @else
                            <x-neuronai-studio::ui.input type="text" wire:model="formName" placeholder="OPENAI_PROD" />
                        @endif
                        <p class="mt-1 text-xs text-muted-foreground">Uppercase letters, digits, underscore. Must start with a letter.</p>
                        @error('formName') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>

                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Value</x-neuronai-studio::ui.label>
                        <div
                            x-data="{ show: {{ $formType === 'generic' ? 'true' : 'false' }} }"
                            class="relative"
                        >
                            <x-neuronai-studio::ui.input
                                x-bind:type="show ? 'text' : 'password'"
                                wire:model="formValue"
                                placeholder="{{ $editingId && $formType === 'credential' ? 'Leave blank to keep existing' : '' }}"
                            />
                            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground" @click="show = !show">
                                <span x-text="show ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>
                        @if ($editingId && $formType === 'credential')
                            <p class="mt-1 text-xs text-muted-foreground">Leave blank to keep the current credential value.</p>
                        @endif
                        @error('formValue') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" wire:click="closeModal">Cancel</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" wire:click="save">Save</x-neuronai-studio::ui.button>
                </div>
            </div>
        </div>
    @endif
</x-neuronai-studio::ui.page>
