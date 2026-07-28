<div>
    @if ($showModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" wire:click.self="close">
            <div class="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-lg" role="dialog" aria-modal="true">
                <h2 class="mb-4 text-lg font-semibold">Create Variable</h2>

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
                        <x-neuronai-studio::ui.input type="text" wire:model="formName" placeholder="OPENAI_PROD" />
                        <p class="mt-1 text-xs text-muted-foreground">Uppercase letters, digits, underscore. Must start with a letter.</p>
                        @error('formName') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>

                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Value</x-neuronai-studio::ui.label>
                        <div x-data="{ show: {{ $formType === 'generic' ? 'true' : 'false' }} }" class="relative">
                            <x-neuronai-studio::ui.input
                                x-bind:type="show ? 'text' : 'password'"
                                wire:model="formValue"
                            />
                            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground" @click="show = !show">
                                <span x-text="show ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>
                        @error('formValue') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" wire:click="close">Cancel</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" wire:click="save">Save</x-neuronai-studio::ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
