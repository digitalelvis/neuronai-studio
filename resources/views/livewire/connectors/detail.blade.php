<div>
@if ($isOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="close">
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl" role="dialog" aria-modal="true" @click.stop>
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-border px-6 py-4">
                <div class="min-w-0 flex-1 text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl bg-muted text-xl font-semibold">
                        @if (! empty($entry['icon']))
                            <img src="{{ $entry['icon'] }}" alt="" class="h-full w-full object-cover" />
                        @else
                            {{ strtoupper(substr((string) ($entry['name'] ?? '?'), 0, 1)) }}
                        @endif
                    </div>
                    <h2 class="text-xl font-semibold">{{ $entry['name'] ?? '' }}</h2>
                    @if (! empty($entry['description']))
                        <p class="mt-2 text-sm text-muted-foreground">{{ $entry['description'] }}</p>
                    @endif
                </div>
                <button type="button" class="shrink-0 text-muted-foreground hover:text-foreground" wire:click="close">×</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4 space-y-6">
                @php($type = (string) ($entry['type'] ?? ''))

                <div class="flex flex-wrap justify-center gap-2">
                    @if ($type === 'plugin' && ! ($entry['installed'] ?? false))
                        <x-neuronai-studio::ui.button wire:click="installPlugin">{{ __('neuronai-studio::plugins.install') }}</x-neuronai-studio::ui.button>
                    @elseif ($type === 'plugin' && ($entry['installed'] ?? false))
                        <x-neuronai-studio::ui.button variant="destructive" wire:click="uninstallPlugin" wire:confirm="{{ __('neuronai-studio::plugins.uninstall_confirm') }}">{{ __('neuronai-studio::plugins.uninstall') }}</x-neuronai-studio::ui.button>
                    @elseif (in_array($type, ['mcp', 'api', 'rag', 'rag_tool', 'endpoint'], true))
                        <x-neuronai-studio::ui.button wire:click="openEdit">{{ __('neuronai-studio::connectors.configure') }}</x-neuronai-studio::ui.button>
                        @if ($type === 'mcp' && ($entry['needs_auth'] ?? false))
                            <x-neuronai-studio::ui.button variant="outline" wire:click="openCredentials">{{ __('neuronai-studio::connectors.connect') }}</x-neuronai-studio::ui.button>
                        @endif
                    @elseif ($type === 'data_source')
                        <x-neuronai-studio::ui.button wire:click="openEdit">{{ __('neuronai-studio::connectors.create_data_source') }}</x-neuronai-studio::ui.button>
                    @endif
                </div>

                @if ($accounts !== [])
                    <section>
                        <h3 class="mb-2 text-sm font-semibold">{{ __('neuronai-studio::plugins.accounts') }}</h3>
                        <div class="space-y-2">
                            @foreach ($accounts as $account)
                                <div class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm">
                                    <span>{{ $account['label'] }}</span>
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($account['connected'])
                                            <x-neuronai-studio::ui.badge variant="published">{{ __('neuronai-studio::plugins.connected') }}</x-neuronai-studio::ui.badge>
                                            @if ($account['supports_manual_auth'] ?? false)
                                                <x-neuronai-studio::ui.button size="sm" variant="outline" wire:click="openCredentials('{{ $account['label'] }}')">{{ __('neuronai-studio::connectors.configure') }}</x-neuronai-studio::ui.button>
                                            @endif
                                        @else
                                            <x-neuronai-studio::ui.badge variant="draft">{{ __('neuronai-studio::plugins.needs_auth') }}</x-neuronai-studio::ui.badge>
                                            @if (($account['supports_oauth'] ?? false) && ($entry['oauth_configured'] ?? false))
                                                <x-neuronai-studio::ui.button size="sm" wire:click="startOAuth({{ $account['id'] }})">{{ __('neuronai-studio::plugins.authenticate') }}</x-neuronai-studio::ui.button>
                                            @endif
                                            @if ($account['supports_manual_auth'] ?? false)
                                                <x-neuronai-studio::ui.button size="sm" variant="outline" wire:click="openCredentials('{{ $account['label'] }}')">{{ __('neuronai-studio::plugins.save_credentials') }}</x-neuronai-studio::ui.button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($skills !== [])
                    <section>
                        <h3 class="mb-2 text-sm font-semibold">{{ __('neuronai-studio::connectors.prompt_examples') }}</h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($skills as $skill)
                                <div class="rounded-lg border border-border bg-muted/30 p-3 text-sm">
                                    <strong>{{ $skill['name'] }}</strong>
                                    @if (! empty($skill['description']))
                                        <p class="mt-1 text-xs text-muted-foreground">{{ \Illuminate\Support\Str::limit($skill['description'], 120) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($mcpConnectors !== [])
                    <section>
                        <h3 class="mb-2 text-sm font-semibold">{{ __('neuronai-studio::plugins.connectors') }}</h3>
                        @foreach ($mcpConnectors as $connector)
                            <div class="mb-2 rounded border border-border p-2 text-sm">
                                <strong>{{ $connector['name'] }}</strong>
                                <code class="ml-2 text-xs">{{ $connector['slug'] }}</code>
                                <span class="ml-2 text-xs uppercase text-muted-foreground">{{ $connector['transport'] }}</span>
                            </div>
                        @endforeach
                    </section>
                @endif

                <section class="rounded-lg bg-muted/40 p-4">
                    <h3 class="mb-3 text-sm font-semibold">{{ __('neuronai-studio::connectors.details') }}</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">{{ __('neuronai-studio::connectors.detail_type') }}</dt>
                            <dd class="font-medium">{{ __('neuronai-studio::connectors.types.'.$type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ __('neuronai-studio::connectors.detail_author') }}</dt>
                            <dd class="font-medium">{{ $entry['author'] ?? '—' }}</dd>
                        </div>
                        @if (! empty($entry['slug']))
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">{{ __('neuronai-studio::connectors.detail_slug') }}</dt>
                                <dd><code class="text-xs">{{ $entry['slug'] }}</code></dd>
                            </div>
                        @endif
                        @if (! empty($entry['version']))
                            <div>
                                <dt class="text-muted-foreground">{{ __('neuronai-studio::plugins.version') }}</dt>
                                <dd class="font-medium">{{ $entry['version'] }}</dd>
                            </div>
                        @endif
                        @if (! empty($entry['auth_mode']))
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">{{ __('neuronai-studio::connectors.detail_auth') }}</dt>
                                <dd class="font-medium">{{ __('neuronai-studio::connectors.auth_modes.'.$entry['auth_mode']) }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        </div>
    </div>
@endif
</div>
