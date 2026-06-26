<x-neuronai-studio::ui.page>
    <form wire:submit="save" class="space-y-4">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-content class="space-y-4 pt-4">
                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Name</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="text" wire:model="name" placeholder="Support basic checks" />
                    @error('name') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Dataset (JSON)</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.textarea wire:model="datasetJson" rows="16" class="font-mono text-sm" placeholder='[{"input":"...","reference":"..."}]' />
                    @error('datasetJson') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                    <p class="text-sm text-muted-foreground">Each case supports <code>input</code>, <code>reference</code>, optional <code>_assertions</code>, and optional <code>tool</code>.</p>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="useFakeProvider" class="rounded border-input" />
                        Use fake AI provider (deterministic runs)
                    </label>
                </x-neuronai-studio::ui.form-group>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <div class="flex flex-wrap gap-2">
            <x-neuronai-studio::ui.button type="submit">Save</x-neuronai-studio::ui.button>
            <x-neuronai-studio::ui.button type="button" variant="secondary" wire:click="runSuite">Run Suite</x-neuronai-studio::ui.button>
            @if ($suite?->exists)
                <x-neuronai-studio::ui.button type="button" variant="ghost" :href="route('neuronai-studio.agents.evals.runs', ['agent' => $agent, 'suite' => $suite])">View Runs</x-neuronai-studio::ui.button>
            @endif
            <x-neuronai-studio::ui.button type="button" variant="ghost" :href="route('neuronai-studio.agents.evals.index', $agent)">Back</x-neuronai-studio::ui.button>
        </div>
    </form>
</x-neuronai-studio::ui.page>
