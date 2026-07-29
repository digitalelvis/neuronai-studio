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
            @elseif (! empty($crumb['editable']))
                <button
                    type="button"
                    id="workflow-breadcrumb-name"
                    class="studio-breadcrumb-edit"
                    title="{{ __('neuronai-studio::ui.actions.edit') }}"
                    onclick="window.dispatchEvent(new CustomEvent('workflow-meta-edit-open'))"
                >
                    <span class="studio-breadcrumb-edit-label">{{ $crumb['label'] }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                </button>
            @else
                <span class="studio-breadcrumb-current">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
