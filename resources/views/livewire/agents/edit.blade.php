@php
    $providerModels = collect(config('neuronai-studio.providers', []))
        ->mapWithKeys(fn ($provider, $key) => [$key => $provider['models'] ?? []])
        ->all();

    $enabledProtocols = array_values(array_keys(array_filter(
        config('neuronai-studio.stream_adapters.protocols', []),
        fn ($p) => !empty($p['enabled'])
    )));

    $integratePrefix = config('neuronai-studio.stream_adapters.route_prefix', 'api/neuronai');
    $agentStreamUrls = $agent?->exists ? [
        'vercel' => url($integratePrefix.'/agents/'.$agent->id.'/stream/vercel'),
        'agui' => url($integratePrefix.'/agents/'.$agent->id.'/stream/agui'),
    ] : null;
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
            enabledProtocols: @json($enabledProtocols),
            streamUrls: @json($agentStreamUrls),
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
                tool_max_runs: @json($tool_max_runs),
                parallel_tool_calls: @json($parallel_tool_calls),
            },
        };
    </script>

    <div id="agent-form-root" class="studio-product-root" wire:ignore></div>
</div>
