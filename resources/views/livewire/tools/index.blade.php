<div>
    <div class="ab-toolbar">
        <a href="{{ route('neuronai-studio.tools.create') }}" class="ab-btn ab-btn-primary">New Tool Class</a>
        <a href="{{ route('neuronai-studio.tools.create', ['kind' => 'webhook']) }}" class="ab-btn">New Webhook Tool</a>
    </div>

    <div class="ab-card ab-mt">
        <div class="ab-form-group">
            <label>Filter</label>
            <input type="text" wire:model.live="filter" class="ab-input" placeholder="Search by name, reference or category...">
        </div>
    </div>

    <div class="ab-card ab-mt">
        @if ($tools->isEmpty())
            <p class="ab-muted">No tools found.</p>
        @else
            <table class="ab-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tools as $tool)
                        <tr wire:key="tool-{{ $tool['ref'] }}">
                            <td>
                                <strong>{{ $tool['label'] }}</strong>
                                @if ($tool['description'])
                                    <div class="ab-muted">{{ \Illuminate\Support\Str::limit($tool['description'], 80) }}</div>
                                @endif
                            </td>
                            <td><span class="ab-badge">{{ $categoryLabels[$tool['category']] ?? $tool['category'] }}</span></td>
                            <td><code>{{ $tool['ref'] }}</code></td>
                            <td>{{ $tool['type'] }}</td>
                            <td class="ab-actions">
                                @if (str_starts_with($tool['ref'], 'tool:db:'))
                                    @php($id = (int) \Illuminate\Support\Str::after($tool['ref'], 'tool:db:'))
                                    <a href="{{ route('neuronai-studio.tools.show', $id) }}">View</a>
                                    <a href="{{ route('neuronai-studio.tools.edit', $id) }}">Edit</a>
                                    <button wire:click="delete({{ $id }})" wire:confirm="Delete this tool?" class="ab-btn-link ab-danger">Delete</button>
                                @elseif (str_starts_with($tool['ref'], 'class:'))
                                    <a href="{{ route('neuronai-studio.tools.registry', ['ref' => $tool['ref']]) }}">View</a>
                                    <a href="{{ route('neuronai-studio.tools.create', ['import' => \Illuminate\Support\Str::after($tool['ref'], 'class:')]) }}">Edit</a>
                                @else
                                    <a href="{{ route('neuronai-studio.tools.registry', ['ref' => $tool['ref']]) }}">View</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
