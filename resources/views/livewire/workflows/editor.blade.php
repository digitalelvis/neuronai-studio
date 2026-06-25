<div>
    @if ($readOnly)
        @php
            $readOnlyBanner = $linkedClassPath
                ? 'Linked to ' . $linkedClassPath . ' — read-only preview. Use Import to Studio from the workflows list to create an editable copy.'
                : 'Read-only preview.';
        @endphp
    @endif

    <script>
        window.__NEURONAI_CANVAS_CONFIG = {
            graph: @json($graph),
            savedGraph: @json($graph),
            nodeTypes: @json($nodeTypes),
            wireId: @json($this->getId()),
            workflowId: @json($workflow?->id),
            workflowName: @json($name),
            workflowDescription: @json($description),
            workflowStatus: @json($status),
            readOnly: @json($readOnly),
            readOnlyBanner: @json($readOnlyBanner ?? null),
            streamUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.run.stream', $workflow) : null),
            resumeUrlTemplate: @json(route('neuronai-studio.workflows.runs.resume.stream', ['run' => '__RUN__'])),
            uploadUrl: @json(route('neuronai-studio.attachments.store')),
            agents: @json($agentsForCanvas),
            tools: @json($toolsForCanvas),
            mcpServers: @json($mcpServersForCanvas),
        };
    </script>

    <div id="workflow-editor-root" class="h-[calc(100vh-3rem)]" wire:ignore></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('workflow-editor-root');
        if (root && window.mountWorkflowEditor) {
            window.mountWorkflowEditor(root, window.__NEURONAI_CANVAS_CONFIG);
        }
    });
</script>
