<div class="studio-product-root flex min-h-0 flex-1 flex-col">
    <script>
        window.__NEURONAI_CHAT_CONFIG = {
            mode: 'agent',
            entityId: @json($agent->id),
            streamUrl: @json(route('neuronai-studio.agents.chat.stream', $agent)),
            uploadUrl: @json(route('neuronai-studio.attachments.store')),
            agentMeta: {
                name: @json($agent->name),
                provider: @json($agent->provider),
                model: @json($agent->model),
                tools: @json(collect($agent->tools)->pluck('ref')->values()->all()),
                mcpServers: @json($agent->mcpBindings->pluck('mcp_server_slug')->values()->all()),
                mcpToolCount: @json($mcpToolCount),
            },
        };
    </script>

    <div id="studio-chat-root" class="min-h-0 flex-1" wire:ignore></div>
</div>
