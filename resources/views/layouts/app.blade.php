<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('neuronai-studio::ui.brand') }}</title>
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/css/studio-ui.css') }}">
    @if (request()->routeIs('neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/workflow-canvas.css') }}">
    @endif
    @if (request()->routeIs('neuronai-studio.agents.playground', 'neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-chat.css') }}">
    @endif
    @if (\DigitalElvis\NeuronAIStudio\Support\StudioLayout::isFormsPage())
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-forms.css') }}">
    @endif
    @if (\DigitalElvis\NeuronAIStudio\Support\StudioLayout::isCodeEditorPage())
        <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-code.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/js/dist/studio-toast.css') }}">
    <script>
        window.__STUDIO_I18N__ = @json([
            'locale' => app()->getLocale(),
            'messages' => \DigitalElvis\NeuronAIStudio\Support\StudioI18n::jsMessages(),
        ]);
        window.__STUDIO_FLASH_TOASTS__ = [
            @if (session('success'))
                { variant: 'success', message: @json(session('success')) },
            @endif
            @if (session('error'))
                { variant: 'error', message: @json(session('error')) },
            @endif
        ];
    </script>
</head>
<body class="bg-background text-foreground">
    <div id="studio-toast-root"></div>
    <div class="studio-shell">
        <aside class="studio-icon-rail" aria-label="{{ __('neuronai-studio::ui.nav.main') }}">
            <div class="flex h-12 items-center justify-center border-b border-border">
                <span class="text-lg" title="{{ __('neuronai-studio::ui.brand') }}">⚡</span>
            </div>
            <nav class="flex flex-1 flex-col py-2">
                <a href="{{ route('neuronai-studio.dashboard') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.dashboard') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.dashboard') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.agents.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.agents.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.agents') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.templates.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.templates.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.templates') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.tools.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.tools.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.tools') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.mcp-servers.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.mcp-servers.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.mcp_servers') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.mcp-endpoints.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.mcp-endpoints.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.mcp_endpoints') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v8"/><path d="m16 6-4 4-4-4"/><rect width="20" height="8" x="2" y="14" rx="2"/><path d="M6 18h.01"/><path d="M10 18h.01"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.knowledge-bases.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.knowledge-bases.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.knowledge_bases') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.workflows.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.workflows.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.workflows') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><path d="M6 9v12"/><circle cx="18" cy="18" r="3"/><path d="M18 15V3"/><path d="m6 9 12 6"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.stream-adapters.index') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.stream-adapters.*') ? 'active' : '' }}" title="{{ __('neuronai-studio::ui.nav.stream_adapters') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                </a>
                <a href="{{ route('neuronai-studio.settings.variables') }}" class="studio-icon-rail-link {{ request()->routeIs('neuronai-studio.settings.*') ? 'active' : '' }}" title="Settings">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
            </nav>
        </aside>
        <div class="studio-main">
            <header class="studio-topbar">
                <div class="flex items-center gap-3">
                    @isset($breadcrumbs)
                        @include('neuronai-studio::components.studio-breadcrumbs', ['breadcrumbs' => $breadcrumbs])
                    @else
                        <span class="text-sm font-medium">{{ $title ?? __('neuronai-studio::ui.brand') }}</span>
                    @endisset
                </div>
                @isset($headerActions)
                    <div class="flex items-center gap-2">{!! $headerActions !!}</div>
                @endisset
            </header>
            <div class="studio-content {{ ($contentFlush ?? false) ? 'studio-content--flush' : '' }}">
                @if (session('success'))
                    <div @class([($contentFlush ?? false) ? 'studio-flash' : 'mb-4'])>
                        <x-neuronai-studio::ui.alert variant="success">{{ session('success') }}</x-neuronai-studio::ui.alert>
                    </div>
                @endif
                @if (session('error'))
                    <div @class([($contentFlush ?? false) ? 'studio-flash' : 'mb-4'])>
                        <x-neuronai-studio::ui.alert variant="error">{{ session('error') }}</x-neuronai-studio::ui.alert>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewire('neuronai-studio.settings.variables.quick-create')
    @if (request()->routeIs('neuronai-studio.workflows.create', 'neuronai-studio.workflows.edit', 'neuronai-studio.workflows.preview'))
        @php($workflowCanvasVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/workflow-canvas.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/workflow-canvas.bundle.js') }}?v={{ $workflowCanvasVersion }}"></script>
    @elseif (request()->routeIs('neuronai-studio.agents.playground'))
        @php($studioChatVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-chat.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-chat.bundle.js') }}?v={{ $studioChatVersion }}"></script>
    @elseif (\DigitalElvis\NeuronAIStudio\Support\StudioLayout::isFormsPage())
        @php($studioFormsVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-forms.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-forms.bundle.js') }}?v={{ $studioFormsVersion }}"></script>
    @elseif (\DigitalElvis\NeuronAIStudio\Support\StudioLayout::isCodeEditorPage())
        @php($studioCodeVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-code.bundle.js')) ?: time())
        <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-code.bundle.js') }}?v={{ $studioCodeVersion }}"></script>
    @endif
    @stack('code-editor')
    @livewireScripts
    @php($studioToastVersion = @filemtime(public_path('vendor/neuronai-studio/js/dist/studio-toast.bundle.js')) ?: time())
    <script src="{{ asset('vendor/neuronai-studio/js/dist/studio-toast.bundle.js') }}?v={{ $studioToastVersion }}"></script>
</body>
</html>
