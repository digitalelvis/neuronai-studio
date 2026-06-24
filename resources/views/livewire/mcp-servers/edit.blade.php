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
            <label>Transport</label>
            <select wire:model.live="transport" class="ab-input">
                <option value="stdio">Stdio</option>
                <option value="http">HTTP</option>
                <option value="sse">SSE</option>
            </select>
        </div>
        <div class="ab-form-group">
            <label>Timeout (seconds)</label>
            <input type="number" wire:model="timeout" class="ab-input" min="1" max="300">
            @error('timeout') <span class="ab-error">{{ $message }}</span> @enderror
        </div>
    </div>

    @if ($transport === 'stdio')
        <div class="ab-form-group">
            <label>Command</label>
            <input type="text" wire:model="command" class="ab-input" placeholder="npx">
            @error('command') <span class="ab-error">{{ $message }}</span> @enderror
        </div>
        <div class="ab-form-group">
            <label>Args (JSON array)</label>
            <textarea wire:model="argsJson" class="ab-input" rows="4" placeholder='["-y", "@modelcontextprotocol/server-filesystem", "/path"]'></textarea>
        </div>
        <div class="ab-form-group">
            <label>Env (JSON object)</label>
            <textarea wire:model="envJson" class="ab-input" rows="3" placeholder='{"API_KEY": "env:MY_API_KEY"}'></textarea>
        </div>
    @else
        <div class="ab-form-group">
            <label>URL</label>
            <input type="url" wire:model="url" class="ab-input" placeholder="https://example.com/mcp">
            @error('url') <span class="ab-error">{{ $message }}</span> @enderror
        </div>
        <div class="ab-form-group">
            <label>Token Env Variable</label>
            <input type="text" wire:model="tokenEnv" class="ab-input" placeholder="TELESCOPE_MCP_TOKEN">
            <p class="ab-muted ab-mt">Reference an environment variable name. Raw tokens are never stored.</p>
            @error('tokenEnv') <span class="ab-error">{{ $message }}</span> @enderror
        </div>
        <div class="ab-form-group">
            <label>Headers (JSON object)</label>
            <textarea wire:model="headersJson" class="ab-input" rows="3" placeholder='{"X-Custom": "value"}'></textarea>
        </div>
    @endif

    <div class="ab-form-row">
        <div class="ab-form-group">
            <label>Only Tools (comma-separated)</label>
            <input type="text" wire:model="onlyTools" class="ab-input" placeholder="tool_one, tool_two">
        </div>
        <div class="ab-form-group">
            <label>Exclude Tools (JSON array)</label>
            <textarea wire:model="excludeToolsJson" class="ab-input" rows="2" placeholder='["tool_to_skip"]'></textarea>
        </div>
    </div>

    <div class="ab-form-group">
        <label class="ab-check">
            <input type="checkbox" wire:model="enabled">
            Enabled
        </label>
    </div>

    <div class="ab-form-actions">
        <button type="button" wire:click="testConnection" class="ab-btn" wire:loading.attr="disabled" wire:target="testConnection">
            <span wire:loading.remove wire:target="testConnection">Test Connection</span>
            <span wire:loading wire:target="testConnection">Testing...</span>
        </button>
        <a href="{{ route('neuronai-studio.mcp-servers.index') }}" class="ab-btn">Cancel</a>
        <button type="submit" class="ab-btn ab-btn-primary">Save MCP Server</button>
    </div>

    @if ($testError)
        <div class="ab-alert ab-mt">{{ $testError }}</div>
    @endif

    @if ($testTools !== [])
        <div class="ab-card ab-mt">
            <h3>Available Tools ({{ count($testTools) }})</h3>
            <ul>
                @foreach ($testTools as $tool)
                    <li><code>{{ $tool }}</code></li>
                @endforeach
            </ul>
        </div>
    @endif
</form>
