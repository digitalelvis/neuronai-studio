export async function previewWorkflowCodeWithLivewire() {
    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (!wireId || !window.Livewire) {
        return { ok: false, error: 'Livewire component not available.' };
    }

    const component = window.Livewire.find(wireId);

    if (!component) {
        return { ok: false, error: 'Livewire component not found.' };
    }

    const graph = window.__workflowGraphExport?.() ?? window.__workflowGraph;
    const config = window.__NEURONAI_CANVAS_CONFIG ?? {};

    if (!graph) {
        return { ok: false, error: 'No workflow graph available.' };
    }

    try {
        const result = await component.call(
            'previewWorkflowCode',
            graph,
            config.workflowName ?? '',
            config.workflowDescription ?? '',
            config.workflowStatus ?? 'draft',
        );

        return { ok: true, ...result };
    } catch (error) {
        return { ok: false, error: error?.message ?? 'Failed to generate code preview.' };
    }
}

export async function exportWorkflowWithLivewire() {
    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (!wireId || !window.Livewire) {
        return { ok: false, error: 'Livewire component not available.' };
    }

    const component = window.Livewire.find(wireId);

    if (!component) {
        return { ok: false, error: 'Livewire component not found.' };
    }

    try {
        await component.call('exportWorkflow');
        return { ok: true };
    } catch (error) {
        return { ok: false, error: error?.message ?? 'Export failed.' };
    }
}
