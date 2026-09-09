<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-header class="flex flex-row items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">{{ $install->name }}</h2>
                <p class="text-sm text-muted-foreground">{{ $install->description }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ __('neuronai-studio::plugins.version') }}: {{ $install->version ?? '—' }}</p>
            </div>
            <x-neuronai-studio::ui.button variant="destructive" size="sm" wire:click="uninstall" wire:confirm="{{ __('neuronai-studio::plugins.uninstall_confirm') }}">{{ __('neuronai-studio::plugins.uninstall') }}</x-neuronai-studio::ui.button>
        </x-neuronai-studio::ui.card-header>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-header>
            <h2 class="text-base font-semibold">{{ __('neuronai-studio::plugins.accounts') }}</h2>
        </x-neuronai-studio::ui.card-header>
        <x-neuronai-studio::ui.card-content class="space-y-4">
            @foreach ($accounts as $account)
                <div class="rounded-md border border-border p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <strong>{{ $account->label }}</strong>
                        @if ($account->isConnected())
                            <x-neuronai-studio::ui.badge variant="published">{{ __('neuronai-studio::plugins.connected') }}</x-neuronai-studio::ui.badge>
                        @else
                            <x-neuronai-studio::ui.badge variant="draft">{{ __('neuronai-studio::plugins.needs_auth') }}</x-neuronai-studio::ui.badge>
                        @endif
                    </div>
                    @if ($account->label === $accountLabel)
                        @forelse ($credentialMap as $envKey => $ref)
                            <x-neuronai-studio::ui.form-group class="mt-2">
                                <x-neuronai-studio::ui.label>{{ $envKey }}</x-neuronai-studio::ui.label>
                                <x-neuronai-studio::ui.input wire:model.live="credentialMap.{{ $envKey }}" placeholder="var:MY_CREDENTIAL" list="studio-variables" />
                            </x-neuronai-studio::ui.form-group>
                        @empty
                            <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::plugins.no_credentials') }}</p>
                        @endforelse
                        @if ($credentialMap !== [])
                            <x-neuronai-studio::ui.button class="mt-3" size="sm" wire:click="saveCredentials">{{ __('neuronai-studio::plugins.authenticate') }}</x-neuronai-studio::ui.button>
                        @endif
                    @endif
                </div>
            @endforeach
            <datalist id="studio-variables">
                @foreach ($variables as $name)
                    <option value="var:{{ $name }}"></option>
                @endforeach
            </datalist>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h2 class="text-base font-semibold">{{ __('neuronai-studio::plugins.connectors') }} ({{ $mcpServers->count() }})</h2>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                @forelse ($mcpServers as $server)
                    <div class="mb-2 rounded border border-border p-2 text-sm">
                        <strong>{{ $server->name }}</strong>
                        <code class="ml-2 text-xs">{{ $server->slug }}</code>
                        <span class="ml-2 text-xs uppercase text-muted-foreground">{{ $server->transport }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::plugins.no_connectors') }}</p>
                @endforelse
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>

        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-header>
                <h2 class="text-base font-semibold">{{ __('neuronai-studio::plugins.skills') }} ({{ $skills->count() }})</h2>
            </x-neuronai-studio::ui.card-header>
            <x-neuronai-studio::ui.card-content>
                @forelse ($skills as $skill)
                    <div class="mb-2 rounded border border-border p-2 text-sm">
                        <strong>{{ $skill->slug }}</strong>
                        <p class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::limit($skill->description, 100) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::plugins.no_skills') }}</p>
                @endforelse
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    </div>
</x-neuronai-studio::ui.page>
