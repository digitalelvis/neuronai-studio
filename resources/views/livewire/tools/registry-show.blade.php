<x-neuronai-studio::ui.page>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h2 class="text-lg font-semibold">{{ $entry['label'] }}</h2>
                @if ($entry['description'])
                    <p class="text-sm text-muted-foreground">{{ $entry['description'] }}</p>
                @endif
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                <x-neuronai-studio::ui.description-list>
                    <x-neuronai-studio::ui.description-item term="Reference"><code>{{ $ref }}</code></x-neuronai-studio::ui.description-item>
                    <x-neuronai-studio::ui.description-item term="Category">{{ $categoryLabel }}</x-neuronai-studio::ui.description-item>
                    <x-neuronai-studio::ui.description-item term="Type">{{ $entry['type'] }}</x-neuronai-studio::ui.description-item>
                </x-neuronai-studio::ui.description-list>
                <div class="mt-4 flex gap-2">
                    @if (str_starts_with($ref, 'class:'))
                        <x-neuronai-studio::ui.button :href="route('neuronai-studio.tools.create', ['import' => \Illuminate\Support\Str::after($ref, 'class:')])">Edit in Builder</x-neuronai-studio::ui.button>
                    @endif
                    <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.index')">Back</x-neuronai-studio::ui.button>
                </div>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <div class="space-y-4">
            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header><h3 class="font-semibold">Configuration</h3></x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content>
                    @if ($config === [])
                        <p class="text-sm text-muted-foreground">No additional configuration.</p>
                    @else
                        <x-neuronai-studio::ui.code-block>{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-neuronai-studio::ui.code-block>
                    @endif
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        </div>
    </div>
</x-neuronai-studio::ui.page>
