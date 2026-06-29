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
                    <x-neuronai-studio::ui.label>Judge Agent</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.select wire:model="judgeAgentId">
                        <option value="">None (string assertions only)</option>
                        @foreach ($agents as $judgeAgent)
                            <option value="{{ $judgeAgent->id }}">{{ $judgeAgent->name }} ({{ $judgeAgent->slug }})</option>
                        @endforeach
                    </x-neuronai-studio::ui.select>
                    @error('judgeAgentId') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                    <p class="text-sm text-muted-foreground">
                        Required for AI judge assertions:
                        <code>correctness</code>, <code>faithfulness</code>, <code>relevance</code>, <code>helpfulness</code>, <code>criteria</code>.
                        <a href="{{ route('neuronai-studio.templates.index', ['category' => 'eval-judge']) }}" class="underline">Create a judge agent from templates</a>.
                    </p>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Dataset (JSON)</x-neuronai-studio::ui.label>
                    <script>
                        window.__NEURONAI_CODE_EDITORS = window.__NEURONAI_CODE_EDITORS || {};
                        window.__NEURONAI_CODE_EDITORS['eval-dataset-editor'] = { value: @json($datasetJson) };
                    </script>
                    <div
                        wire:ignore
                        id="eval-dataset-editor"
                        data-neuron-code-editor
                        data-wire-id="{{ $this->getId() }}"
                        data-field="datasetJson"
                        data-language="json"
                        data-min-height="384px"
                        class="min-h-[384px]"
                    ></div>
                    @error('datasetJson') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                    <p class="text-sm text-muted-foreground">
                        Each case supports <code>input</code>, <code>reference</code>, optional <code>context</code>, optional <code>_assertions</code>, and optional <code>tool</code>.
                        Judge types: <code>correctness</code>, <code>faithfulness</code>, <code>relevance</code>, <code>helpfulness</code>, <code>criteria</code>.
                    </p>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="useFakeProvider" class="rounded border-input" />
                        Use fake AI provider for the agent under test (deterministic runs; judge still uses real provider)
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
