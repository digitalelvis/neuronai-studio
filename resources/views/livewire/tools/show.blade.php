<div class="ab-grid ab-grid-2">
    <div class="ab-card">
        <h2>{{ $tool->name }}</h2>
        <p class="ab-muted">{{ $tool->description }}</p>

        <dl class="ab-dl ab-mt">
            <dt>Reference</dt>
            <dd><code>{{ $tool->bindingRef() }}</code></dd>
            <dt>Type</dt>
            <dd>{{ $tool->type }}</dd>
            @if ($tool->type === 'builder')
                <dt>Tool Name</dt>
                <dd><code>{{ $tool->config['tool_name'] ?? '' }}</code></dd>
                <dt>Class</dt>
                <dd><code>{{ $tool->config['class_path'] ?? 'Not exported yet' }}</code></dd>
            @else
                <dt>Method</dt>
                <dd>{{ $tool->config['method'] ?? 'GET' }}</dd>
                <dt>URL</dt>
                <dd><code>{{ $tool->config['url'] ?? '' }}</code></dd>
            @endif
        </dl>

        <div class="ab-form-actions ab-mt">
            <a href="{{ route('neuronai-studio.tools.edit', $tool) }}" class="ab-btn ab-btn-primary">Edit</a>
            <a href="{{ route('neuronai-studio.tools.index') }}" class="ab-btn">Back</a>
        </div>
    </div>

    <div>
        <div class="ab-card">
            <h3>Input Schema</h3>
            @if (empty($tool->input_schema))
                <p class="ab-muted">No input properties defined.</p>
            @else
                <pre class="ab-code">{{ json_encode($tool->input_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </div>

        @if ($tool->type === 'builder' && $this->generatedPreview)
            <div class="ab-card ab-mt">
                <h3>Generated Class</h3>
                <pre class="ab-code ab-code-preview"><code>{{ $this->generatedPreview }}</code></pre>
            </div>
        @endif

        <div class="ab-card ab-mt">
            <h3>Agent Binding Example</h3>
            <pre class="ab-code">{{ json_encode(['ref' => $tool->bindingRef(), 'config' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="ab-card ab-mt">
            <h3>Agents Using This Tool</h3>
            @if ($agentsUsing->isEmpty())
                <p class="ab-muted">No agents attached yet.</p>
            @else
                <ul>
                    @foreach ($agentsUsing as $agent)
                        <li><a href="{{ route('neuronai-studio.agents.edit', $agent) }}">{{ $agent->name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
