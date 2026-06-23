<form wire:submit="save" class="ab-form ab-card">
    <div class="ab-form-group">
        <label>Name</label>
        <input type="text" wire:model="name" class="ab-input" required>
        @error('name') <span class="ab-error">{{ $message }}</span> @enderror
    </div>

    <div class="ab-form-group">
        <label>Description</label>
        <textarea wire:model="description" class="ab-input" rows="2"></textarea>
    </div>

    <div class="ab-form-row">
        <div class="ab-form-group">
            <label>Provider</label>
            <select wire:model.live="provider" class="ab-input">
                @foreach ($providers as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ab-form-group">
            <label>Model</label>
            <select wire:model="model" class="ab-input">
                @foreach ($models as $modelOption)
                    <option value="{{ $modelOption }}">{{ $modelOption }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ab-form-group">
        <label>Instructions (System Prompt)</label>
        <textarea wire:model="instructions" class="ab-input" rows="8" placeholder="You are a helpful assistant..."></textarea>
    </div>

    <div class="ab-form-group">
        <label>Tools</label>
        <p class="ab-muted ab-mb">Select tools and toolkits the agent can use during conversations.</p>

        @foreach ($toolCategories as $categoryKey => $categoryLabel)
            @php($items = $toolCatalog->get($categoryKey, collect()))
            @if ($items->isNotEmpty())
                <div class="ab-tool-group ab-mt">
                    <strong>{{ $categoryLabel }}</strong>
                    <div class="ab-tool-list">
                        @foreach ($items as $tool)
                            <label class="ab-tool-item">
                                <input
                                    type="checkbox"
                                    wire:model="selectedToolRefs"
                                    value="{{ $tool['ref'] }}"
                                >
                                <span>
                                    <strong>{{ $tool['label'] }}</strong>
                                    @if ($tool['description'])
                                        <span class="ab-muted"> — {{ $tool['description'] }}</span>
                                    @endif
                                    <code class="ab-tool-ref">{{ $tool['ref'] }}</code>
                                </span>
                            </label>

                            @if (in_array($tool['ref'], $selectedToolRefs) && in_array($tool['type'], ['toolkit', 'mcp']))
                                <div class="ab-tool-advanced" wire:key="advanced-{{ $tool['ref'] }}">
                                    <div class="ab-form-row">
                                        <div class="ab-form-group">
                                            <label>Only (comma-separated)</label>
                                            <input
                                                type="text"
                                                class="ab-input"
                                                wire:model="toolAdvanced.{{ $tool['ref'] }}.only"
                                                placeholder="tool_name_1, tool_name_2"
                                            >
                                        </div>
                                        <div class="ab-form-group">
                                            <label>Exclude (comma-separated)</label>
                                            <input
                                                type="text"
                                                class="ab-input"
                                                wire:model="toolAdvanced.{{ $tool['ref'] }}.exclude"
                                                placeholder="tool_name_1, tool_name_2"
                                            >
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if ($toolCatalog->flatten(1)->isEmpty())
            <p class="ab-muted">No tools registered. Configure toolkits in config/neuronai-studio.php.</p>
        @endif
    </div>

    <div class="ab-form-actions">
        <a href="{{ route('neuronai-studio.agents.index') }}" class="ab-btn">Cancel</a>
        <button type="submit" class="ab-btn ab-btn-primary">Save Agent</button>
    </div>
</form>
