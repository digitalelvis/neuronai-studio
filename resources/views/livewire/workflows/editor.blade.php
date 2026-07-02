@php
    $providerModels = collect(config('neuronai-studio.providers', []))
        ->mapWithKeys(fn ($provider, $key) => [$key => $provider['models'] ?? []])
        ->all();
@endphp

<div class="studio-product-root flex min-h-0 flex-1 flex-col">
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
            streamUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.trace.stream', $workflow) : null),
            resumeUrlTemplate: @json(route('neuronai-studio.workflows.traces.resume.stream', ['trace' => '__TRACE__'])),
            tracesIndexUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.traces.index', $workflow) : null),
            traceShowUrlTemplate: @json(route('neuronai-studio.workflows.traces.show', ['trace' => '__TRACE__'])),
            traceShowJsonUrlTemplate: @json(route('neuronai-studio.workflows.traces.show.json', ['trace' => '__TRACE__'])),
            uploadUrl: @json(route('neuronai-studio.attachments.store')),
            agents: @json($agentsForCanvas),
            knowledgeBases: @json($knowledgeBasesForCanvas),
            ragSearchUrlTemplate: @json(route('neuronai-studio.knowledge-bases.search', ['knowledgeBase' => '__KB__'])),
            tools: @json($toolsForCanvas),
            mcpServers: @json($mcpServersForCanvas),
            outputClasses: @json($outputClassesForCanvas),
            providers: @json($providers),
            providerModels: @json($providerModels),
            defaultProvider: @json(config('neuronai-studio.default_provider')),
            defaultModel: @json(config('neuronai-studio.default_model')),
        };
    </script>

    <div id="workflow-editor-root" class="min-h-0 flex-1" wire:ignore></div>
</div>
