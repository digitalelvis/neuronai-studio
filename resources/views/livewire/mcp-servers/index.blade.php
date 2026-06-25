<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="pt-4">
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>Filter</x-neuronai-studio::ui.label>
                <x-neuronai-studio::ui.input type="text" wire:model.live="filter" placeholder="Search by name, slug or transport..." />
            </x-neuronai-studio::ui.form-group>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        @if ($servers === [])
            <x-neuronai-studio::ui.empty-state title="No MCP servers configured">
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.mcp-servers.create')">New MCP Server</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Slug</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Transport</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Source</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($servers as $slug => $server)
                        <x-neuronai-studio::ui.table-row wire:key="mcp-{{ $slug }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $server['label'] ?? $slug }}</strong>
                                @if (! empty($server['description']))
                                    <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($server['description'], 80) }}</div>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell><code class="text-xs">{{ $slug }}</code></x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ strtoupper($server['transport'] ?? 'stdio') }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.badge variant="secondary">{{ ($server['source'] ?? '') === 'config' ? 'System' : 'Database' }}</x-neuronai-studio::ui.badge>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                @if ($server['enabled'] ?? true)
                                    <x-neuronai-studio::ui.badge variant="published">Enabled</x-neuronai-studio::ui.badge>
                                @else
                                    <x-neuronai-studio::ui.badge variant="draft">Disabled</x-neuronai-studio::ui.badge>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    @if (($server['source'] ?? '') === 'database' && ! empty($server['id']))
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.mcp-servers.edit', $server['id'])">Edit</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $server['id'] }})" wire:confirm="Delete this MCP server?" class="text-destructive">Delete</x-neuronai-studio::ui.button>
                                    @else
                                        <span class="text-sm text-muted-foreground">Read-only</span>
                                    @endif
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
