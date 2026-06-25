<script>
    window.__NEURONAI_TOOL_BUILDER_CONFIG = {
        wireId: @json($this->getId()),
        cancelUrl: @json(route('neuronai-studio.tools.index')),
        initial: {
            toolKind: @json($toolKind),
            name: @json($name),
            toolName: @json($toolName),
            description: @json($description),
            method: @json($method),
            url: @json($url),
            headersJson: @json($headersJson),
            invokeBody: @json($invokeBody),
            inputSchema: @json($inputSchema),
        },
    };
</script>

<div id="tool-builder-root" class="h-[calc(100vh-3rem)]" wire:ignore></div>
