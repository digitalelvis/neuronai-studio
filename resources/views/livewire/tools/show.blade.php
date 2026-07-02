<x-neuronai-studio::ui.page>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h2 class="text-lg font-semibold">{{ $tool->name }}</h2>
                <p class="text-sm text-muted-foreground">{{ $tool->description }}</p>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                <x-neuronai-studio::ui.description-list>
                    <x-neuronai-studio::ui.description-item term="Reference"><code>{{ $tool->bindingRef() }}</code></x-neuronai-studio::ui.description-item>
                    <x-neuronai-studio::ui.description-item term="Type">{{ $tool->type }}</x-neuronai-studio::ui.description-item>
                    @if ($tool->type === 'builder')
                        <x-neuronai-studio::ui.description-item term="Tool Name"><code>{{ $tool->config['tool_name'] ?? '' }}</code></x-neuronai-studio::ui.description-item>
                        <x-neuronai-studio::ui.description-item term="Class"><code>{{ $tool->config['class_path'] ?? 'Not exported yet' }}</code></x-neuronai-studio::ui.description-item>
                    @elseif ($tool->type === 'rag')
                        <x-neuronai-studio::ui.description-item term="Tool Name"><code>{{ $tool->config['tool_name'] ?? '' }}</code></x-neuronai-studio::ui.description-item>
                        <x-neuronai-studio::ui.description-item term="Knowledge Base ID">{{ $tool->config['knowledge_base_id'] ?? '—' }}</x-neuronai-studio::ui.description-item>
                        @if (! empty($tool->config['top_k']))
                            <x-neuronai-studio::ui.description-item term="Top K">{{ $tool->config['top_k'] }}</x-neuronai-studio::ui.description-item>
                        @endif
                        @if (isset($tool->config['threshold']) && $tool->config['threshold'] !== '')
                            <x-neuronai-studio::ui.description-item term="Threshold">{{ $tool->config['threshold'] }}</x-neuronai-studio::ui.description-item>
                        @endif
                    @else
                        <x-neuronai-studio::ui.description-item term="Method">{{ $tool->config['method'] ?? 'GET' }}</x-neuronai-studio::ui.description-item>
                        <x-neuronai-studio::ui.description-item term="URL"><code>{{ $tool->config['url'] ?? '' }}</code></x-neuronai-studio::ui.description-item>
                    @endif
                </x-neuronai-studio::ui.description-list>
                <div class="mt-4 flex gap-2">
                    <x-neuronai-studio::ui.button :href="route('neuronai-studio.tools.edit', $tool)">Edit</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.index')">Back</x-neuronai-studio::ui.button>
                </div>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <div class="space-y-4">
            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header><h3 class="font-semibold">Input Schema</h3></x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content>
                    @if (empty($tool->input_schema))
                        <p class="text-sm text-muted-foreground">No input properties defined.</p>
                    @else
                        <x-neuronai-studio::ui.code-block>{{ json_encode($tool->input_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</x-neuronai-studio::ui.code-block>
                    @endif
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>

            @if ($tool->type === 'builder' && $this->generatedPreview)
                <x-neuronai-studio::ui.card>
                    <x-neuronai-studio::ui.card-header><h3 class="font-semibold">Generated Class</h3></x-neuronai-studio::ui.card-header>
                    <x-neuronai-studio::ui.card-content>
                        <x-neuronai-studio::ui.code-block>{{ $this->generatedPreview }}</x-neuronai-studio::ui.code-block>
                    </x-neuronai-studio::ui.card-content>
                </x-neuronai-studio::ui.card>
            @endif

            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header><h3 class="font-semibold">Agents Using This Tool</h3></x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content>
                    @if ($agentsUsing->isEmpty())
                        <p class="text-sm text-muted-foreground">No agents attached yet.</p>
                    @else
                        <ul class="space-y-1 text-sm">
                            @foreach ($agentsUsing as $agent)
                                <li><a href="{{ route('neuronai-studio.agents.edit', $agent) }}" class="text-primary hover:underline">{{ $agent->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        </div>
    </div>
</x-neuronai-studio::ui.page>
