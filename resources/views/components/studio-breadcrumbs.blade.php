@php
    $isProductPage = request()->routeIs(
        'neuronai-studio.agents.playground',
        'neuronai-studio.workflows.create',
        'neuronai-studio.workflows.edit',
        'neuronai-studio.workflows.preview',
    );
    $breadcrumbs = $breadcrumbs ?? [];
@endphp

@if (count($breadcrumbs) > 0)
    <nav class="studio-breadcrumb" aria-label="Breadcrumb">
        @foreach ($breadcrumbs as $index => $crumb)
            @if ($index > 0)
                <span class="studio-breadcrumb-sep">/</span>
            @endif
            @if (! empty($crumb['url']) && $index < count($breadcrumbs) - 1)
                <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
            @else
                <span class="studio-breadcrumb-current">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
