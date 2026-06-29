<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class StudioLayout
{
    /** @param  array<int, array{label: string, url?: string}>  $breadcrumbs */
    public static function params(
        array $breadcrumbs,
        ?string $title = null,
        ?string $headerActions = null,
        bool $contentFlush = false,
    ): array {
        return array_filter([
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'headerActions' => $headerActions,
            'contentFlush' => $contentFlush,
        ], fn ($value) => $value !== null);
    }

    public static function isProductPage(): bool
    {
        return request()->routeIs(
            'neuronai-studio.agents.playground',
            'neuronai-studio.workflows.create',
            'neuronai-studio.workflows.edit',
            'neuronai-studio.workflows.preview',
        );
    }

    public static function isFormsPage(): bool
    {
        return request()->routeIs(
            'neuronai-studio.agents.create',
            'neuronai-studio.agents.edit',
            'neuronai-studio.tools.create',
            'neuronai-studio.tools.edit',
            'neuronai-studio.workflows.traces.show',
        );
    }

    public static function isCodeEditorPage(): bool
    {
        return request()->routeIs(
            'neuronai-studio.agents.evals.create',
            'neuronai-studio.agents.evals.edit',
        );
    }
}
