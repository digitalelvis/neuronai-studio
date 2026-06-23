<div class="ab-grid ab-grid-2">
    <div class="ab-card">
        <h2>{{ $entry['label'] }}</h2>
        @if ($entry['description'])
            <p class="ab-muted">{{ $entry['description'] }}</p>
        @endif

        <dl class="ab-dl ab-mt">
            <dt>Reference</dt>
            <dd><code>{{ $ref }}</code></dd>
            <dt>Category</dt>
            <dd>{{ $categoryLabel }}</dd>
            <dt>Type</dt>
            <dd>{{ $entry['type'] }}</dd>
        </dl>

        <div class="ab-form-actions ab-mt">
            @if (str_starts_with($ref, 'class:'))
                <a href="{{ route('neuronai-studio.tools.create', ['import' => \Illuminate\Support\Str::after($ref, 'class:')]) }}" class="ab-btn ab-btn-primary">Edit in Builder</a>
            @endif
            <a href="{{ route('neuronai-studio.tools.index') }}" class="ab-btn">Back</a>
        </div>
    </div>

    <div>
        <div class="ab-card">
            <h3>Configuration</h3>
            @if ($config === [])
                <p class="ab-muted">No additional configuration. This tool is registered via config or scanned from the filesystem.</p>
            @else
                <pre class="ab-code">{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div>

        <div class="ab-card ab-mt">
            <h3>Agent Binding Example</h3>
            <pre class="ab-code">{{ json_encode(['ref' => $ref, 'config' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        @if (str_starts_with($ref, 'toolkit:'))
            <div class="ab-card ab-mt">
                <h3>Toolkit Options</h3>
                <p class="ab-muted">Use <code>only</code> or <code>exclude</code> in the agent tool binding to filter individual tools inside this toolkit.</p>
            </div>
        @endif

        @if (str_starts_with($ref, 'mcp:'))
            <div class="ab-card ab-mt">
                <h3>MCP Options</h3>
                <p class="ab-muted">Configure this server in <code>config/neuronai-studio.php</code> under <code>mcp_servers</code>.</p>
            </div>
        @endif
    </div>
</div>
