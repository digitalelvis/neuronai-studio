<x-neuronai-studio::ui.page>
    <form wire:submit="save" class="space-y-4">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h3 class="font-semibold">{{ __('neuronai-studio::skills.metadata') }}</h3>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.display_name') }}</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input wire:model="displayName" placeholder="My Skill" />
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.name') }}</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input wire:model="slug" placeholder="my-skill" />
                        @error('slug') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted-foreground">{{ __('neuronai-studio::skills.name_hint') }}</p>
                    </x-neuronai-studio::ui.form-group>
                </div>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.description') }}</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.textarea wire:model="description" rows="3" />
                    @error('description') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </x-neuronai-studio::ui.form-group>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.categories_label') }}</x-neuronai-studio::ui.label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach ($categories as $key => $label)
                                <label class="inline-flex items-center gap-1.5 rounded-full border border-input px-2.5 py-1 text-xs {{ in_array($key, $selectedCategories, true) ? 'bg-primary/10 border-primary/40' : 'bg-background' }}">
                                    <input type="checkbox" value="{{ $key }}" wire:model="selectedCategories" class="rounded border-input" />
                                    {{ __('neuronai-studio::skills.categories.'.$key) !== 'neuronai-studio::skills.categories.'.$key ? __('neuronai-studio::skills.categories.'.$key) : $label }}
                                </label>
                            @endforeach
                        </div>
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.cover_image') }}</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input wire:model="coverImage" placeholder="https://… or assets/cover.png" />
                    </x-neuronai-studio::ui.form-group>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.license') }}</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input wire:model="license" />
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.compatibility') }}</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input wire:model="compatibility" />
                    </x-neuronai-studio::ui.form-group>
                </div>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h3 class="font-semibold">{{ __('neuronai-studio::skills.instructions') }}</h3>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                <x-neuronai-studio::ui.textarea wire:model="body" rows="16" class="font-mono text-sm" placeholder="# Instructions..." />
                @error('body') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header class="flex flex-row items-center justify-between">
                <h3 class="font-semibold">{{ __('neuronai-studio::skills.resources') }}</h3>
                <div class="flex gap-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" size="sm" wire:click="addResourceFile('references/')">{{ __('neuronai-studio::skills.add_reference') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" variant="outline" size="sm" wire:click="addResourceFile('scripts/')">{{ __('neuronai-studio::skills.add_script') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" variant="outline" size="sm" wire:click="addResourceFile('assets/')">{{ __('neuronai-studio::skills.add_asset') }}</x-neuronai-studio::ui.button>
                </div>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content class="space-y-4">
                @forelse ($resourceFiles as $index => $file)
                    <div class="space-y-2 rounded-md border border-border p-3" wire:key="res-{{ $index }}">
                        <x-neuronai-studio::ui.form-group>
                            <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.path') }}</x-neuronai-studio::ui.label>
                            <x-neuronai-studio::ui.input wire:model="resourceFiles.{{ $index }}.path" placeholder="references/guide.md" />
                        </x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.form-group>
                            <x-neuronai-studio::ui.label>{{ __('neuronai-studio::skills.content') }}</x-neuronai-studio::ui.label>
                            <x-neuronai-studio::ui.textarea wire:model="resourceFiles.{{ $index }}.content" rows="6" class="font-mono text-sm" />
                        </x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.button type="button" variant="ghost" size="sm" class="text-destructive" wire:click="removeResourceFile({{ $index }})">{{ __('neuronai-studio::ui.actions.remove') }}</x-neuronai-studio::ui.button>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::skills.resources_empty') }}</p>
                @endforelse
                @error('resourceFiles') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h3 class="font-semibold">{{ __('neuronai-studio::skills.preview') }}</h3>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                <pre class="overflow-auto rounded-md bg-muted p-3 text-xs">{{ $preview }}</pre>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <div class="flex gap-2">
            <x-neuronai-studio::ui.button type="submit">{{ __('neuronai-studio::ui.actions.save') }}</x-neuronai-studio::ui.button>
            <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.skills.index')">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
        </div>
    </form>
</x-neuronai-studio::ui.page>
