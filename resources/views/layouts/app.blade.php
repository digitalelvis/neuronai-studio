<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'NeuronAI Studio' }}</title>
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/css/studio-ui.css') }}">
    @if (request()->routeIs('neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/workflow-canvas.css') }}">
    @endif
    @if (request()->routeIs('neuronai-studio.agents.playground', 'neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-chat.css') }}">
    @endif
    @if (\ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout::isFormsPage())
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-forms.css') }}">
    @endif
</head>
<body class="bg-background text-foreground">
    <div class="studio-shell">
        <aside class="studio-icon-rail" aria-label="Main navigation">
            <div class="flex h-12 items-center justify-center border-b border-border">
                <span class="text-lg" title="NeuronAI Studio">⚡</span>
            </div>
            <nav class="flex flex-1 flex-col py-2">
                <a href="{{ route('neuronai-studio.dashboard') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.dashboard') ? 'active' : '' }}" title="Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.agents.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.agents.*') ? 'active' : '' }}" title="Agents">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.templates.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.templates.*') ? 'active' : '' }}" title="Templates">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.tools.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.tools.*') ? 'active' : '' }}" title="Tools">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.mcp-servers.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.mcp-servers.*') ? 'active' : '' }}" title="MCP Servers">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.workflows.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.workflows.*') ? 'active' : '' }}" title="Workflows">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><path d="M6 9v12"/><circle cx="18" cy="18" r="3"/><path d="M18 15V3"/><path d="m6 9 12 6"/></svg>
                </a>
            </nav>
        </aside>
        <div class="studio-main">
            <header class="studio-topbar">
                <div class="flex items-center gap-3">
                    @isset($breadcrumbs)
                        @include('neuronai-studio::components.studio-breadcrumbs', ['breadcrumbs' => $breadcrumbs])
                    @else
                        <span class="text-sm font-medium">{{ $title ?? 'NeuronAI Studio' }}</span>
                    @endisset
                </div>
                @isset($headerActions)
                    <div class="flex items-center gap-2">{!! $headerActions !!}</div>
                @endisset
            </header>
            <div class="studio-content {{ ($contentFlush ?? false) ? 'studio-content--flush' : '' }}">
                @if (session('success'))
                    <div class="mb-4">
                        <x-neuronai-studio::ui.alert variant="success">{{ session('success') }}</x-neuronai-studio::ui.alert>
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-4">
                        <x-neuronai-studio::ui.alert variant="error">{{ session('error') }}</x-neuronai-studio::ui.alert>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
    @if (request()->routeIs('neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        @php($workflowCanvasVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/workflow-canvas.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/workflow-canvas.bundle.js') }}?v={{ $workflowCanvasVersion }}"></script>
    @elseif (request()->routeIs('neuronai-studio.agents.playground'))
        @php($studioChatVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-chat.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-chat.bundle.js') }}?v={{ $studioChatVersion }}"></script>
    @elseif (\ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout::isFormsPage())
        @php($studioFormsVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-forms.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-forms.bundle.js') }}?v={{ $studioFormsVersion }}"></script>
    @endif
    @livewireScripts
</body>
</html>
