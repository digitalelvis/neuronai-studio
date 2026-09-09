<div>
    <x-neuronai-studio::ui.form-group>
        <x-neuronai-studio::ui.label>{{ __('neuronai-studio::connectors.mcp_json_label') }}</x-neuronai-studio::ui.label>
        <x-neuronai-studio::ui.textarea wire:model="json" rows="12" placeholder='{
  "mcpServers": {
    "example": {
      "command": "npx",
      "args": ["-y", "mcp-server-example"]
    }
  }
}'></x-neuronai-studio::ui.textarea>
    </x-neuronai-studio::ui.form-group>

    @if ($error !== '')
        <p class="mb-3 text-sm text-destructive">{{ $error }}</p>
    @endif

    <div class="flex justify-end gap-2">
        <x-neuronai-studio::ui.button wire:click="import" wire:loading.attr="disabled">{{ __('neuronai-studio::connectors.mcp_json_import') }}</x-neuronai-studio::ui.button>
    </div>
</div>
