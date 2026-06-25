<div class="studio-product-root flex min-h-0 flex-1 flex-col">
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

    <div id="tool-builder-root" class="studio-product-root" wire:ignore></div>
</div>
