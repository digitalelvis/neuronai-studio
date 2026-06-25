@php
    $providerModels = collect(config('neuronai-studio.providers', []))
        ->mapWithKeys(fn ($provider, $key) => [$key => $provider['models'] ?? []])
        ->all();
@endphp

<div class="studio-product-root flex min-h-0 flex-1 flex-col">
    <script>
        window.__NEURONAI_AGENT_FORM_CONFIG = {
            wireId: @json($this->getId()),
            cancelUrl: @json(route('neuronai-studio.agents.index')),
            providers: @json($providers),
            providerModels: @json($providerModels),
            models: @json($models),
            defaultProvider: @json(config('neuronai-studio.default_provider')),
            toolList: @json($toolList),
            mcpServers: @json($mcpServers),
            initial: {
                name: @json($name),
                description: @json($description),
                provider: @json($provider),
                model: @json($model),
                instructions: @json($instructions),
                selectedToolRefs: @json($selectedToolRefs),
                toolAdvanced: @json($toolAdvanced),
                selectedMcpSlugs: @json($selectedMcpSlugs),
                mcpAdvanced: @json($mcpAdvanced),
            },
        };
    </script>

    <div id="agent-form-root" class="studio-product-root" wire:ignore></div>
</div>
