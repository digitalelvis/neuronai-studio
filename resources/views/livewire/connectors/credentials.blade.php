<div>
@if ($isOpen)
    <x-neuronai-studio::ui.modal maxWidth="lg" closeAction="close">
        <x-slot:header>
            <h2 class="text-lg font-semibold">{{ __('neuronai-studio::connectors.credentials_title') }}</h2>
        </x-slot:header>

        @if (in_array($authMode, ['oauth', 'oauth_or_token'], true))
            <div class="mb-4 rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">{{ __('neuronai-studio::connectors.auth_modes.'.$authMode) }}</p>
                <p class="mt-1">{{ __('neuronai-studio::connectors.oauth_hint') }}</p>
            </div>
        @endif

        @if ($mode === 'plugin')
            @forelse ($credentialMap as $envKey => $ref)
                <x-neuronai-studio::ui.form-group class="mb-3">
                    <x-neuronai-studio::ui.label>{{ $envKey }}</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::variable-input
                        wire-model="credentialMap.{{ $envKey }}"
                        :sensitive="true"
                        placeholder="var:MY_CREDENTIAL"
                        hint="{{ __('neuronai-studio::connectors.api_key_hint') }}"
                    />
                </x-neuronai-studio::ui.form-group>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('neuronai-studio::plugins.no_credentials') }}</p>
            @endforelse
        @else
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>{{ __('neuronai-studio::connectors.api_key') }}</x-neuronai-studio::ui.label>
                <x-neuronai-studio::variable-input
                    wire-model="tokenEnv"
                    :sensitive="true"
                    placeholder="var:MY_API_KEY"
                    hint="{{ __('neuronai-studio::connectors.api_key_hint') }}"
                />
            </x-neuronai-studio::ui.form-group>
        @endif

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-neuronai-studio::ui.button variant="outline" wire:click="close">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
                <x-neuronai-studio::ui.button wire:click="save">{{ __('neuronai-studio::connectors.connect') }}</x-neuronai-studio::ui.button>
            </div>
        </x-slot:footer>
    </x-neuronai-studio::ui.modal>
@endif
</div>
