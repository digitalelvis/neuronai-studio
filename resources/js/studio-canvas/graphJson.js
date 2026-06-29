import { extractGraph } from './GraphJsonPanel';

export function downloadWorkflowJson(includeMeta = false) {
    const config = window.__NEURONAI_CANVAS_CONFIG ?? {};
    const graph = window.__workflowGraphExport?.() ?? window.__workflowGraph;

    if (!graph) {
        return;
    }

    const payload = includeMeta
        ? {
              meta: {
                  name: config.workflowName ?? '',
                  description: config.workflowDescription ?? '',
                  status: config.workflowStatus ?? 'draft',
              },
              graph,
          }
        : graph;

    const name = (config.workflowName || 'workflow').replace(/[^a-z0-9-_]+/gi, '-').toLowerCase();
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${name}.json`;
    anchor.click();
    URL.revokeObjectURL(url);
}

export function isCanvasDirty() {
    const saved = window.__NEURONAI_CANVAS_CONFIG?.savedGraph;
    const current = window.__workflowGraphExport?.() ?? window.__workflowGraph;

    if (!saved || !current) {
        return false;
    }

    return JSON.stringify(saved) !== JSON.stringify(current);
}

export async function validateGraphWithLivewire(graph) {
    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (!wireId || !window.Livewire) {
        return { valid: false, errors: ['Livewire component not available.'] };
    }

    const component = window.Livewire.find(wireId);

    if (!component) {
        return { valid: false, errors: ['Livewire component not found.'] };
    }

    return component.call('validateGraphPayload', graph);
}

export async function applyGraphImport(graph) {
    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (wireId && window.Livewire) {
        const component = window.Livewire.find(wireId);

        if (component) {
            await component.call('applyImportedGraph', graph);
        }
    }

    window.dispatchEvent(new CustomEvent('workflow-canvas-load-graph', { detail: graph }));
    window.__NEURONAI_CANVAS_CONFIG.savedGraph = graph;
    window.__workflowGraphDirty = false;
    window.dispatchEvent(new CustomEvent('workflow-graph-changed'));
}

export async function importGraphFromText(text, { confirmIfDirty = true } = {}) {
    let parsed;

    try {
        parsed = JSON.parse(text);
    } catch {
        return { ok: false, errors: ['Invalid JSON syntax.'] };
    }

    const graph = extractGraph(parsed);

    if (!graph) {
        return { ok: false, errors: ['JSON must contain a graph with a nodes array.'] };
    }

    if (confirmIfDirty && isCanvasDirty()) {
        const confirmed = window.confirm('The canvas has unsaved changes. Replace with imported graph?');

        if (!confirmed) {
            return { ok: false, cancelled: true };
        }
    }

    const result = await validateGraphWithLivewire(graph);

    if (!result?.valid) {
        return { ok: false, errors: result?.errors ?? ['Graph validation failed.'] };
    }

    await applyGraphImport(graph);

    return { ok: true };
}

export function ensureImportModal() {
    let modal = document.getElementById('workflow-json-import-modal');

    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = 'workflow-json-import-modal';
    modal.className = 'studio-import-modal-overlay';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="studio-import-modal" role="dialog" aria-labelledby="workflow-json-import-title">
            <h3 id="workflow-json-import-title" class="text-lg font-semibold">Import workflow JSON</h3>
            <p class="text-sm text-muted-foreground mt-2">Paste graph JSON or upload a .json file. Metadata envelope <code>{ meta, graph }</code> is supported.</p>
            <textarea id="workflow-json-import-textarea" class="mt-3 flex min-h-[240px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-mono shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" rows="12"></textarea>
            <p id="workflow-json-import-error" class="text-sm text-destructive mt-2" hidden></p>
            <div class="flex flex-wrap items-center gap-2 mt-4">
                <label class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground">
                    Upload file
                    <input type="file" id="workflow-json-import-file" accept=".json,application/json" hidden>
                </label>
                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground" id="workflow-json-import-cancel">Cancel</button>
                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90" id="workflow-json-import-apply">Import</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const textarea = modal.querySelector('#workflow-json-import-textarea');
    const errorEl = modal.querySelector('#workflow-json-import-error');
    const fileInput = modal.querySelector('#workflow-json-import-file');

    modal.querySelector('#workflow-json-import-cancel').addEventListener('click', () => {
        modal.hidden = true;
        errorEl.hidden = true;
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.hidden = true;
            errorEl.hidden = true;
        }
    });

    fileInput.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        textarea.value = await file.text();
        fileInput.value = '';
    });

    modal.querySelector('#workflow-json-import-apply').addEventListener('click', async () => {
        errorEl.hidden = true;
        const result = await importGraphFromText(textarea.value);

        if (result.cancelled) {
            return;
        }

        if (!result.ok) {
            errorEl.textContent = (result.errors ?? ['Import failed.']).join(' ');
            errorEl.hidden = false;
            return;
        }

        modal.hidden = true;
        textarea.value = '';
    });

    return modal;
}

export function openImportModal() {
    const modal = ensureImportModal();
    modal.hidden = false;
    modal.querySelector('#workflow-json-import-textarea')?.focus();
}
