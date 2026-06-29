<x-neuronai-studio::ui.page>
    <form wire:submit="save">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-content class="space-y-4 pt-4">
                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Name</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="text" wire:model="name" required />
                    @error('name') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Description</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.textarea wire:model="description" rows="2"></x-neuronai-studio::ui.textarea>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-row>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Transport</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.select wire:model.live="transport">
                            <option value="stdio">Stdio</option>
                            <option value="http">HTTP</option>
                            <option value="sse">SSE</option>
                        </x-neuronai-studio::ui.select>
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Timeout (seconds)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="number" wire:model="timeout" min="1" max="300" />
                        @error('timeout') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                </x-neuronai-studio::ui.form-row>

                @if ($transport === 'stdio')
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Command</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="text" wire:model="command" placeholder="npx" />
                        @error('command') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Args (JSON array)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.textarea wire:model="argsJson" rows="4" placeholder='["-y", "@modelcontextprotocol/server-filesystem", "/path"]'></x-neuronai-studio::ui.textarea>
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Env (JSON object)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.textarea wire:model="envJson" rows="3" placeholder='{"API_KEY": "env:MY_API_KEY"}'></x-neuronai-studio::ui.textarea>
                    </x-neuronai-studio::ui.form-group>
                @else
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>URL</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="url" wire:model="url" placeholder="https://example.com/mcp" />
                        @error('url') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Token Env Variable</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="text" wire:model="tokenEnv" placeholder="TELESCOPE_MCP_TOKEN" />
                        <p class="mt-1 text-xs text-muted-foreground">Reference an environment variable name. Raw tokens are never stored.</p>
                        @error('tokenEnv') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Headers (JSON object)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.textarea wire:model="headersJson" rows="3" placeholder='{"X-Custom": "value"}'></x-neuronai-studio::ui.textarea>
                    </x-neuronai-studio::ui.form-group>
                @endif

                <x-neuronai-studio::ui.form-row>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Only Tools (comma-separated)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="text" wire:model="onlyTools" placeholder="tool_one, tool_two" />
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Exclude Tools (JSON array)</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.textarea wire:model="excludeToolsJson" rows="2" placeholder='["tool_to_skip"]'></x-neuronai-studio::ui.textarea>
                    </x-neuronai-studio::ui.form-group>
                </x-neuronai-studio::ui.form-row>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="enabled" class="rounded border-input">
                    Enabled
                </label>

                <div class="flex flex-wrap gap-2 pt-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
                        <span wire:loading.remove wire:target="testConnection">Test Connection</span>
                        <span wire:loading wire:target="testConnection">Testing...</span>
                    </x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.mcp-servers.index')">Cancel</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="submit">Save MCP Server</x-neuronai-studio::ui.button>
                </div>

                @if ($testError)
                    <x-neuronai-studio::ui.alert variant="error">{{ $testError }}</x-neuronai-studio::ui.alert>
                @endif
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        @if ($testTools !== [])
            <x-neuronai-studio::ui.card class="mt-4">
                <x-neuronai-studio::ui.card-header>
                    <h3 class="font-semibold">Available Tools ({{ count($testTools) }})</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content>
                    <ul class="space-y-1 font-mono text-xs">
                        @foreach ($testTools as $tool)
                            <li><code>{{ $tool }}</code></li>
                        @endforeach
                    </ul>
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        @endif
    </form>
</x-neuronai-studio::ui.page>
