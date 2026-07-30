<x-neuronai-studio::ui.page>
<form wire:submit="save" class="space-y-4">
    @unless ($featureEnabled)
        <x-neuronai-studio::ui.card class="border-amber-500/40">
            <x-neuronai-studio::ui.card-content class="pt-4 text-sm text-muted-foreground">
                {{ __('neuronai-studio::ui.mcp_endpoints.feature_disabled') }}
                <code class="text-xs">NEURONAI_STUDIO_MCP_ENDPOINTS_ENABLED=true</code>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endunless

    @if ($plainApiKey)
        <x-neuronai-studio::ui.card class="border-emerald-500/40">
            <x-neuronai-studio::ui.card-content class="space-y-2 pt-4">
                <p class="text-sm font-medium">{{ __('neuronai-studio::ui.mcp_endpoints.api_key_once') }}</p>
                <code class="block break-all rounded bg-muted px-3 py-2 text-xs">{{ $plainApiKey }}</code>
                <p class="text-xs text-muted-foreground">{{ __('neuronai-studio::ui.mcp_endpoints.api_key_once_hint') }}</p>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endif

    <div class="flex gap-2 border-b border-border pb-2">
        <x-neuronai-studio::ui.button type="button" size="sm" :variant="$activeTab === 'general' ? 'default' : 'ghost'" wire:click="setTab('general')">General</x-neuronai-studio::ui.button>
        <x-neuronai-studio::ui.button type="button" size="sm" :variant="$activeTab === 'bindings' ? 'default' : 'ghost'" wire:click="setTab('bindings')">Bindings</x-neuronai-studio::ui.button>
        <x-neuronai-studio::ui.button type="button" size="sm" :variant="$activeTab === 'connect' ? 'default' : 'ghost'" wire:click="setTab('connect')">Connect</x-neuronai-studio::ui.button>
    </div>

    @if ($activeTab === 'general')
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-content class="space-y-4 pt-4">
                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>{{ __('neuronai-studio::ui.table.name') }}</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="text" wire:model="name" required />
                    @error('name') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Description</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.textarea wire:model="description" rows="3"></x-neuronai-studio::ui.textarea>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Timeout (seconds)</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="number" wire:model="timeoutSeconds" min="1" max="3600" />
                </x-neuronai-studio::ui.form-group>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="enabled" class="rounded border-border" />
                    {{ __('neuronai-studio::ui.mcp_endpoints.enabled_label') }}
                </label>

                @if ($endpoint?->exists)
                    <div class="rounded border border-border p-3 text-sm">
                        <div class="mb-2 text-muted-foreground">API key prefix: <code>{{ $apiKeyPrefix ?: '—' }}</code></div>
                        <x-neuronai-studio::ui.button type="button" variant="outline" size="sm" wire:click="rotateKey" wire:confirm="{{ __('neuronai-studio::ui.confirm.rotate_mcp_endpoint_key') }}">
                            {{ __('neuronai-studio::ui.actions.rotate_api_key') }}
                        </x-neuronai-studio::ui.button>
                    </div>
                @endif
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endif

    @if ($activeTab === 'bindings')
        <div class="grid gap-4 lg:grid-cols-2">
            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="text-sm font-medium">Tools</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($catalog['tools'] as $tool)
                        <label class="flex cursor-pointer items-start gap-2 rounded border border-border p-2 text-sm hover:bg-muted/40">
                            <input
                                type="checkbox"
                                class="mt-1"
                                @checked($this->isBound('tool', $tool['ref']))
                                wire:click="toggleBinding('tool', '{{ $tool['ref'] }}')"
                            />
                            <span>
                                <strong>{{ $tool['label'] ?? $tool['ref'] }}</strong>
                                <code class="ml-1 text-xs text-muted-foreground">{{ $tool['ref'] }}</code>
                                @if (! empty($tool['description']))
                                    <div class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::limit($tool['description'], 100) }}</div>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-muted-foreground">No studio tools available.</p>
                    @endforelse
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>

            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="text-sm font-medium">Toolkits</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="max-h-80 space-y-3 overflow-y-auto">
                    @forelse ($catalog['toolkits'] as $toolkit)
                        @php
                            $bound = $this->isBound('toolkit', $toolkit['ref']);
                            $binding = collect($bindings)->first(fn ($b) => $b['kind'] === 'toolkit' && $b['ref'] === $toolkit['ref']);
                        @endphp
                        <div class="rounded border border-border p-2 text-sm">
                            <label class="flex cursor-pointer items-start gap-2">
                                <input
                                    type="checkbox"
                                    class="mt-1"
                                    @checked($bound)
                                    wire:click="toggleBinding('toolkit', '{{ $toolkit['ref'] }}')"
                                />
                                <span>
                                    <strong>{{ $toolkit['label'] ?? $toolkit['ref'] }}</strong>
                                    <code class="ml-1 text-xs text-muted-foreground">{{ $toolkit['ref'] }}</code>
                                </span>
                            </label>
                            @if ($bound && $binding)
                                <div class="mt-2 space-y-2 pl-6">
                                    <x-neuronai-studio::ui.input
                                        type="text"
                                        class="text-xs"
                                        placeholder="only (comma-separated tool names)"
                                        value="{{ implode(', ', $binding['only'] ?? []) }}"
                                        wire:change="updateToolkitFilter('{{ $toolkit['ref'] }}', 'only', $event.target.value)"
                                    />
                                    <x-neuronai-studio::ui.input
                                        type="text"
                                        class="text-xs"
                                        placeholder="exclude (comma-separated tool names)"
                                        value="{{ implode(', ', $binding['exclude'] ?? []) }}"
                                        wire:change="updateToolkitFilter('{{ $toolkit['ref'] }}', 'exclude', $event.target.value)"
                                    />
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No toolkits configured.</p>
                    @endforelse
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>

            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="text-sm font-medium">Agents</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($catalog['agents'] as $agent)
                        @php $ref = (string) $agent->id; @endphp
                        <label class="flex cursor-pointer items-start gap-2 rounded border border-border p-2 text-sm hover:bg-muted/40">
                            <input
                                type="checkbox"
                                class="mt-1"
                                @checked($this->isBound('agent', $ref))
                                wire:click="toggleBinding('agent', '{{ $ref }}')"
                            />
                            <span>
                                <strong>{{ $agent->name }}</strong>
                                <div class="text-xs text-muted-foreground">{{ $agent->slug }}</div>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-muted-foreground">No agents yet.</p>
                    @endforelse
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>

            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="text-sm font-medium">Workflows</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($catalog['workflows'] as $workflow)
                        @php $ref = (string) $workflow->id; @endphp
                        <label class="flex cursor-pointer items-start gap-2 rounded border border-border p-2 text-sm hover:bg-muted/40">
                            <input
                                type="checkbox"
                                class="mt-1"
                                @checked($this->isBound('workflow', $ref))
                                wire:click="toggleBinding('workflow', '{{ $ref }}')"
                            />
                            <span>
                                <strong>{{ $workflow->name }}</strong>
                                <div class="text-xs text-muted-foreground">{{ $workflow->slug }}</div>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-muted-foreground">No workflows yet.</p>
                    @endforelse
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        </div>

        @if ($bindings !== [])
            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="text-sm font-medium">{{ __('neuronai-studio::ui.mcp_endpoints.overrides') }}</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="space-y-3 pt-2">
                    @foreach ($bindings as $index => $binding)
                        <div class="grid gap-2 rounded border border-border p-3 md:grid-cols-3" wire:key="binding-meta-{{ $binding['kind'] }}-{{ $binding['ref'] }}">
                            <div class="text-xs text-muted-foreground md:col-span-3">
                                <x-neuronai-studio::ui.badge variant="secondary">{{ $binding['kind'] }}</x-neuronai-studio::ui.badge>
                                <code>{{ $binding['ref'] }}</code>
                            </div>
                            <x-neuronai-studio::ui.input
                                type="text"
                                class="text-xs"
                                placeholder="MCP tool name override"
                                wire:model="bindings.{{ $index }}.tool_name"
                            />
                            <x-neuronai-studio::ui.input
                                type="text"
                                class="text-xs md:col-span-2"
                                placeholder="MCP tool description override"
                                wire:model="bindings.{{ $index }}.tool_description"
                            />
                        </div>
                    @endforeach
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        @endif
    @endif

    @if ($activeTab === 'connect')
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-content class="space-y-4 pt-4">
                @if (! $endpoint?->exists)
                    <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::ui.mcp_endpoints.save_before_connect') }}</p>
                @else
                    <div>
                        <x-neuronai-studio::ui.label>Endpoint URL</x-neuronai-studio::ui.label>
                        <code class="mt-1 block break-all rounded bg-muted px-3 py-2 text-xs">{{ $connectUrl }}</code>
                    </div>

                    <div>
                        <x-neuronai-studio::ui.label>Cursor / Claude mcp.json</x-neuronai-studio::ui.label>
                        <pre class="mt-1 overflow-x-auto rounded bg-muted p-3 text-xs">{{ json_encode($mcpJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        <p class="mt-2 text-xs text-muted-foreground">{{ __('neuronai-studio::ui.mcp_endpoints.connect_hint') }}</p>
                    </div>

                    <div>
                        <x-neuronai-studio::ui.label>Exposed tools preview</x-neuronai-studio::ui.label>
                        @if ($previewTools === [])
                            <p class="mt-1 text-sm text-muted-foreground">No tools exposed yet. Add bindings and save.</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($previewTools as $tool)
                                    <li>
                                        <code>{{ $tool['name'] }}</code>
                                        <span class="text-muted-foreground">— {{ $tool['description'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Test with MCP Inspector:
                        <code>npx @modelcontextprotocol/inspector</code>
                    </p>
                @endif
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endif

    <div class="flex justify-end gap-2">
        <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.mcp-endpoints.index')">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
        <x-neuronai-studio::ui.button type="submit">{{ __('neuronai-studio::ui.actions.save') }}</x-neuronai-studio::ui.button>
    </div>
</form>
</x-neuronai-studio::ui.page>
