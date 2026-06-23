<form wire:submit="save" class="ab-tool-builder">
    <div class="ab-form-actions ab-mb">
        <label class="ab-muted">Tool type:</label>
        <select wire:model.live="toolKind" class="ab-input ab-input-inline">
            <option value="builder">PHP Class Builder</option>
            <option value="webhook">Webhook</option>
        </select>
    </div>

    <div class="ab-grid ab-grid-2">
        <div class="ab-card">
            <h3>Definition</h3>

            <div class="ab-form-group">
                <label>Display Name</label>
                <input type="text" wire:model.live="name" class="ab-input" required placeholder="Check Server Test">
                @error('name') <span class="ab-error">{{ $message }}</span> @enderror
            </div>

            @if ($toolKind === 'builder')
                <div class="ab-form-group">
                    <label>Tool Name (function identifier)</label>
                    <input type="text" wire:model.live="toolName" class="ab-input" required placeholder="check_server_test" pattern="[a-z0-9_]+">
                    @error('toolName') <span class="ab-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="ab-form-group">
                <label>Description</label>
                <textarea wire:model.live="description" class="ab-input" rows="3" required placeholder="Describe when the agent should use this tool..."></textarea>
                @error('description') <span class="ab-error">{{ $message }}</span> @enderror
            </div>

            @if ($toolKind === 'webhook')
                <div class="ab-form-row">
                    <div class="ab-form-group">
                        <label>HTTP Method</label>
                        <select wire:model="method" class="ab-input">
                            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $httpMethod)
                                <option value="{{ $httpMethod }}">{{ $httpMethod }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ab-form-group">
                        <label>URL</label>
                        <input type="url" wire:model="url" class="ab-input" placeholder="https://api.example.com/search?q={query}">
                        @error('url') <span class="ab-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="ab-form-group">
                    <label>Headers (JSON)</label>
                    <textarea wire:model="headersJson" class="ab-input ab-code-editor" rows="4">{}</textarea>
                    @error('headersJson') <span class="ab-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="ab-form-group">
                <div class="ab-toolbar">
                    <label>Properties</label>
                    <button type="button" wire:click="addProperty" class="ab-btn">Add Property</button>
                </div>

                @foreach ($inputSchema as $index => $property)
                    <div class="ab-property-row" wire:key="property-{{ $index }}">
                        <input type="text" wire:model.live="inputSchema.{{ $index }}.name" class="ab-input" placeholder="name">
                        <select wire:model.live="inputSchema.{{ $index }}.type" class="ab-input">
                            @foreach (['string', 'integer', 'number', 'boolean'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model.live="inputSchema.{{ $index }}.description" class="ab-input" placeholder="description">
                        <label class="ab-check"><input type="checkbox" wire:model="inputSchema.{{ $index }}.required"> Required</label>
                        <button type="button" wire:click="removeProperty({{ $index }})" class="ab-btn ab-danger">×</button>
                    </div>
                    @error("inputSchema.{$index}.name") <span class="ab-error">{{ $message }}</span> @enderror
                @endforeach
            </div>

            @if ($toolKind === 'builder')
                <div class="ab-form-group">
                    <label><code>{{ $this->invokeSignature }}</code></label>
                    <textarea
                        wire:model.live="invokeBody"
                        class="ab-input ab-code-editor"
                        rows="10"
                        spellcheck="false"
                        placeholder="return 'Result for: ' . $example;"
                    ></textarea>
                    @error('invokeBody') <span class="ab-error">{{ $message }}</span> @enderror
                    <p class="ab-muted ab-mt">Write only the method body. Parameters are generated from properties above.</p>
                </div>
            @endif
        </div>

        @if ($toolKind === 'builder')
            <div class="ab-card ab-code-panel">
                <h3>Generated Class Preview</h3>
                <pre class="ab-code ab-code-preview"><code>{{ $this->generatedPreview }}</code></pre>
            </div>
        @endif
    </div>

    <div class="ab-form-actions ab-mt">
        <a href="{{ route('neuronai-studio.tools.index') }}" class="ab-btn">Cancel</a>
        @if ($tool?->exists && $toolKind === 'builder')
            <button type="button" wire:click="exportPhp" class="ab-btn">Re-export PHP</button>
        @endif
        <button type="submit" class="ab-btn ab-btn-primary">
            {{ $toolKind === 'builder' ? 'Save & Export Class' : 'Save Webhook' }}
        </button>
    </div>
</form>
