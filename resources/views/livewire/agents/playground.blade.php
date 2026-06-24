<div>
    <div class="ab-card ab-mb">
        <h2>{{ $agent->name }}</h2>
        <p class="ab-muted">{{ $agent->provider }} / {{ $agent->model }}</p>

        @if (! empty($agent->tools))
            <p class="ab-muted ab-mt">
                <strong>Tools:</strong>
                {{ collect($agent->tools)->pluck('ref')->implode(', ') }}
            </p>
        @endif

        @if ($agent->mcpBindings->isNotEmpty())
            <p class="ab-muted ab-mt">
                <strong>MCP Servers:</strong>
                {{ $agent->mcpBindings->pluck('mcp_server_slug')->implode(', ') }}
                @if ($mcpToolCount > 0)
                    ({{ $mcpToolCount }} MCP tool{{ $mcpToolCount === 1 ? '' : 's' }} available)
                @endif
            </p>
        @endif
    </div>

    <script>
        window.__NEURONAI_CHAT_CONFIG = {
            mode: 'agent',
            entityId: @json($agent->id),
            streamUrl: @json(route('neuronai-studio.agents.chat.stream', $agent)),
            uploadUrl: @json(route('neuronai-studio.attachments.store')),
        };
    </script>

    <div class="ab-card ab-playground-layout" wire:ignore>
        <div id="studio-chat-root" class="ab-playground-chat-root"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('studio-chat-root');
        if (root && window.mountStudioChat) {
            window.mountStudioChat(root, window.__NEURONAI_CHAT_CONFIG);
        }
    });
</script>
