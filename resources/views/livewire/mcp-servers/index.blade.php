<div>
    <div class="ab-toolbar">
        <a href="{{ route('neuronai-studio.mcp-servers.create') }}" class="ab-btn ab-btn-primary">New MCP Server</a>
    </div>

    <div class="ab-card ab-mt">
        <div class="ab-form-group">
            <label>Filter</label>
            <input type="text" wire:model.live="filter" class="ab-input" placeholder="Search by name, slug or transport...">
        </div>
    </div>

    <div class="ab-card ab-mt">
        @if ($servers === [])
            <p class="ab-muted">No MCP servers configured.</p>
        @else
            <table class="ab-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Transport</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($servers as $slug => $server)
                        <tr wire:key="mcp-{{ $slug }}">
                            <td>
                                <strong>{{ $server['label'] ?? $slug }}</strong>
                                @if (! empty($server['description']))
                                    <div class="ab-muted">{{ \Illuminate\Support\Str::limit($server['description'], 80) }}</div>
                                @endif
                            </td>
                            <td><code>{{ $slug }}</code></td>
                            <td>{{ strtoupper($server['transport'] ?? 'stdio') }}</td>
                            <td>
                                @if (($server['source'] ?? '') === 'config')
                                    <span class="ab-badge">System</span>
                                @else
                                    <span class="ab-badge">Database</span>
                                @endif
                            </td>
                            <td>
                                @if ($server['enabled'] ?? true)
                                    <span class="ab-badge ab-badge-published">Enabled</span>
                                @else
                                    <span class="ab-badge">Disabled</span>
                                @endif
                            </td>
                            <td class="ab-actions">
                                @if (($server['source'] ?? '') === 'database' && ! empty($server['id']))
                                    <a href="{{ route('neuronai-studio.mcp-servers.edit', $server['id']) }}">Edit</a>
                                    <button wire:click="delete({{ $server['id'] }})" wire:confirm="Delete this MCP server?" class="ab-btn-link ab-danger">Delete</button>
                                @else
                                    <span class="ab-muted">Read-only</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
