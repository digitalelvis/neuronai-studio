@php
    $providerModels = collect(config('neuronai-studio.providers', []))
        ->mapWithKeys(fn ($provider, $key) => [$key => $provider['models'] ?? []])
        ->all();

    $enabledProtocols = array_values(array_keys(array_filter(
        config('neuronai-studio.stream_adapters.protocols', []),
        fn ($p) => !empty($p['enabled'])
    )));

    $integratePrefix = config('neuronai-studio.stream_adapters.route_prefix', 'api/neuronai');
    $integrateStreamUrls = $workflow?->exists ? [
        'vercel' => url($integratePrefix.'/workflows/'.$workflow->id.'/stream/vercel'),
        'agui' => url($integratePrefix.'/workflows/'.$workflow->id.'/stream/agui'),
    ] : null;

    $integrateResumeUrls = $workflow?->exists ? [
        'vercel' => url($integratePrefix.'/workflows/traces/__TRACE__/resume/vercel'),
        'agui' => url($integratePrefix.'/workflows/traces/__TRACE__/resume/agui'),
    ] : null;
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
            threadsIndexUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.chat.threads.index', $workflow) : null),
            tracesIndexUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.traces.index', $workflow) : null),
            traceShowUrlTemplate: @json(route('neuronai-studio.workflows.traces.show', ['run' => '__TRACE__'])),
            traceShowJsonUrlTemplate: @json(route('neuronai-studio.workflows.traces.show.json', ['run' => '__TRACE__'])),
            uploadUrl: @json(route('neuronai-studio.attachments.store')),
            agents: @json($agentsForCanvas),
            knowledgeBases: @json($knowledgeBasesForCanvas),
            ragSearchUrlTemplate: @json(route('neuronai-studio.knowledge-bases.search', ['knowledgeBase' => '__KB__'])),
            tools: @json($toolsForCanvas),
            mcpServers: @json($mcpServersForCanvas),
            outputClasses: @json($outputClassesForCanvas),
            providers: @json($providers),
            providerModels: @json($providerModels),
            enabledProtocols: @json($enabledProtocols),
            integrateStreamUrls: @json($integrateStreamUrls),
            integrateResumeUrls: @json($integrateResumeUrls),
            defaultProvider: @json(config('neuronai-studio.default_provider')),
            defaultModel: @json(config('neuronai-studio.default_model')),
            canExport: @json(\DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard::canExport()),
            canPreview: @json(\DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard::canPreview()),
        };
    </script>

    <div id="workflow-editor-root" class="min-h-0 flex-1" wire:ignore></div>
</div>
