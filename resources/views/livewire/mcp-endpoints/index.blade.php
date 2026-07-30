<x-neuronai-studio::ui.page>
    @unless ($featureEnabled)
        <x-neuronai-studio::ui.card class="mb-4 border-amber-500/40">
            <x-neuronai-studio::ui.card-content class="pt-4 text-sm text-muted-foreground">
                {{ __('neuronai-studio::ui.mcp_endpoints.feature_disabled') }}
                <code class="text-xs">NEURONAI_STUDIO_MCP_ENDPOINTS_ENABLED=true</code>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endunless

    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="pt-4">
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>Filter</x-neuronai-studio::ui.label>
                <x-neuronai-studio::ui.input type="text" wire:model.live="filter" placeholder="Search by name or slug..." />
            </x-neuronai-studio::ui.form-group>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        @if ($endpoints->isEmpty())
            <x-neuronai-studio::ui.empty-state :title="__('neuronai-studio::ui.empty.mcp_endpoints_title')">
                <p class="mb-4 text-sm text-muted-foreground">{{ __('neuronai-studio::ui.empty.mcp_endpoints_description') }}</p>
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.mcp-endpoints.create')">{{ __('neuronai-studio::ui.actions.new_mcp_endpoint') }}</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>{{ __('neuronai-studio::ui.table.name') }}</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Slug</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Bindings</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>{{ __('neuronai-studio::ui.table.status') }}</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($endpoints as $endpoint)
                        <x-neuronai-studio::ui.table-row wire:key="mcp-endpoint-{{ $endpoint->id }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $endpoint->name }}</strong>
                                @if ($endpoint->description)
                                    <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($endpoint->description, 80) }}</div>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell><code class="text-xs">{{ $endpoint->slug }}</code></x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $endpoint->bindings_count }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                @if ($endpoint->enabled)
                                    <x-neuronai-studio::ui.badge variant="published">Enabled</x-neuronai-studio::ui.badge>
                                @else
                                    <x-neuronai-studio::ui.badge variant="draft">Disabled</x-neuronai-studio::ui.badge>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.mcp-endpoints.edit', $endpoint)">{{ __('neuronai-studio::ui.actions.edit') }}</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $endpoint->id }})" wire:confirm="{{ __('neuronai-studio::ui.confirm.delete_mcp_endpoint') }}" class="text-destructive">{{ __('neuronai-studio::ui.actions.delete') }}</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
