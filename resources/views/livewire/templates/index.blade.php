<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        <x-neuronai-studio::ui.card-content class="pt-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 items-end">
                <x-neuronai-studio::ui.form-group label="Type" for="typeFilter">
                    <x-neuronai-studio::ui.select id="typeFilter" wire:model.live="typeFilter">
                        <option value="all">All</option>
                        <option value="agent">Agents</option>
                        <option value="workflow">Workflows</option>
                    </x-neuronai-studio::ui.select>
                </x-neuronai-studio::ui.form-group>
                @if ($typeFilter === 'all' || $typeFilter === 'workflow')
                    <x-neuronai-studio::ui.form-group label="Complexity" for="complexityFilter">
                        <x-neuronai-studio::ui.select id="complexityFilter" wire:model.live="complexityFilter">
                            <option value="all">All</option>
                            <option value="basic">Basic</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </x-neuronai-studio::ui.select>
                    </x-neuronai-studio::ui.form-group>
                @endif
            </div>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    @if ($templates === [])
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.empty-state title="No templates" description="No templates match the current filters." />
        </x-neuronai-studio::ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <x-neuronai-studio::ui.card wire:key="template-{{ $template['type'] }}-{{ $template['id'] }}">
                    <x-neuronai-studio::ui.card-content class="pt-6 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold leading-none">{{ $template['name'] }}</h3>
                            <x-neuronai-studio::ui.badge>{{ ucfirst($template['type']) }}</x-neuronai-studio::ui.badge>
                        </div>

                        @if ($template['description'])
                            <p class="text-sm text-muted-foreground">{{ $template['description'] }}</p>
                        @endif

                        <div class="flex flex-wrap gap-1.5">
                            @if (! empty($template['complexity']))
                                <x-neuronai-studio::ui.badge variant="secondary">{{ ucfirst($template['complexity']) }}</x-neuronai-studio::ui.badge>
                            @endif
                            @if (! empty($template['category']))
                                <x-neuronai-studio::ui.badge variant="outline">{{ $template['category'] }}</x-neuronai-studio::ui.badge>
                            @endif
                            @foreach ($template['tags'] ?? [] as $tag)
                                <x-neuronai-studio::ui.badge variant="outline">{{ $tag }}</x-neuronai-studio::ui.badge>
                            @endforeach
                        </div>

                        @if ($template['type'] === 'workflow' && ! empty($template['node_types']))
                            <p class="text-xs text-muted-foreground">
                                Nodes: {{ implode(', ', $template['node_types']) }}
                            </p>
                        @endif

                        @if ($template['type'] === 'workflow' && ! empty($template['agents']))
                            <p class="text-xs text-muted-foreground">
                                Agents: {{ implode(', ', $template['agents']) }}
                            </p>
                        @endif

                        <x-neuronai-studio::ui.button
                            type="button"
                            wire:click="useTemplate('{{ $template['type'] }}', '{{ $template['id'] }}')"
                        >
                            Use Template
                        </x-neuronai-studio::ui.button>
                    </x-neuronai-studio::ui.card-content>
                </x-neuronai-studio::ui.card>
            @endforeach
        </div>
    @endif
</x-neuronai-studio::ui.page>
