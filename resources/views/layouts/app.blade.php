<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NeuronAI Studio' }}</title>
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('vendor/neuronai-studio/css/neuronai-studio.css') }}">
</head>
<body class="ab-body">
    <div class="ab-layout">
        <aside class="ab-sidebar">
            <div class="ab-brand">
                <span class="ab-brand-icon">⚡</span>
                NeuronAI Studio
            </div>
            <nav class="ab-nav">
                <a href="{{ route('neuronai-studio.dashboard') }}" class="ab-nav-link {{ request()->routeIs('neuronai-studio.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('neuronai-studio.agents.index') }}" class="ab-nav-link {{ request()->routeIs('neuronai-studio.agents.*') ? 'active' : '' }}">Agents</a>
                <a href="{{ route('neuronai-studio.workflows.index') }}" class="ab-nav-link {{ request()->routeIs('neuronai-studio.workflows.*') ? 'active' : '' }}">Workflows</a>
            </nav>
        </aside>
        <main class="ab-main">
            <header class="ab-header">
                <h1>{{ $title ?? 'NeuronAI Studio' }}</h1>
            </header>
            @if (session('success'))
                <div class="ab-alert ab-alert-success">{{ session('success') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
    <script src="{{ asset('vendor/neuronai-studio/js/canvas/workflow-canvas.js') }}" defer></script>
</body>
</html>
