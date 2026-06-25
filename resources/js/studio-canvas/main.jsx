import { createRoot } from 'react-dom/client';
import WorkflowEditorShell from './WorkflowEditorShell';
import { openImportModal } from './graphJson';
import '../../css/globals.css';
import './canvas.css';

const editorRoots = new WeakMap();

function syncMetadataToLivewire() {
    const config = window.__NEURONAI_CANVAS_CONFIG;
    const wireId = config?.wireId;

    if (!wireId || !window.Livewire) {
        return;
    }

    const component = window.Livewire.find(wireId);
    if (!component) {
        return;
    }

    component.set('name', config.workflowName ?? '');
    component.set('description', config.workflowDescription ?? '');
    component.set('status', config.workflowStatus ?? 'draft');
}

function exportGraphForSave() {
    return window.__workflowGraphExport?.() ?? window.__workflowGraph;
}

function mergePendingNodeUpdate(graph, pendingUpdate) {
    if (!graph?.nodes || !pendingUpdate?.id) {
        return graph;
    }

    return {
        ...graph,
        nodes: graph.nodes.map((node) =>
            node.id === pendingUpdate.id ? { ...node, data: { ...(node.data || {}), ...pendingUpdate.data } } : node,
        ),
    };
}

function captureInspectorFlushUpdate() {
    let pendingUpdate = null;

    const captureHandler = (event) => {
        pendingUpdate = event.detail;
    };

    window.addEventListener('canvas-node-updated', captureHandler);
    window.dispatchEvent(new CustomEvent('canvas-inspector-flush'));
    window.removeEventListener('canvas-node-updated', captureHandler);

    return pendingUpdate;
}

function saveGraphToLivewire(graphOverride = null) {
    const graph = graphOverride ?? exportGraphForSave();
    if (!graph) {
        return;
    }

    syncMetadataToLivewire();

    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;
    if (wireId && window.Livewire) {
        const component = window.Livewire.find(wireId);
        if (component) {
            component.call('saveGraph', graph);
        }
    }

    window.__NEURONAI_CANVAS_CONFIG.savedGraph = graph;
    window.__workflowGraphDirty = false;
}

function flushInspectorAndSave() {
    const pendingUpdate = captureInspectorFlushUpdate();

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            const graph = mergePendingNodeUpdate(exportGraphForSave(), pendingUpdate);
            saveGraphToLivewire(graph);
        });
    });
}

function bindSaveHandler() {
    if (window.__neuronaiCanvasSaveBound) {
        return;
    }
    window.__neuronaiCanvasSaveBound = true;

    window.addEventListener('workflow-canvas-save', () => {
        flushInspectorAndSave();
    });
}

async function saveGraphBeforeRun() {
    const pendingUpdate = captureInspectorFlushUpdate();

    await new Promise((resolve) => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(resolve);
        });
    });

    const graph = mergePendingNodeUpdate(exportGraphForSave(), pendingUpdate);
    syncMetadataToLivewire();

    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (!graph || !wireId || !window.Livewire) {
        return false;
    }

    const component = window.Livewire.find(wireId);
    if (!component) {
        return false;
    }

    await component.call('saveGraph', graph);
    window.__NEURONAI_CANVAS_CONFIG.savedGraph = graph;
    window.__workflowGraphDirty = false;
    return true;
}

function bindPaletteDrag() {
    if (window.__neuronaiCanvasDragBound) {
        return;
    }
    window.__neuronaiCanvasDragBound = true;

    document.addEventListener('dragstart', (event) => {
        const item = event.target.closest('[data-canvas-node-type]');
        if (!item) {
            return;
        }

        const type = item.dataset.canvasNodeType;
        event.dataTransfer.setData('application/x-neuronai-node', type);
        event.dataTransfer.setData('text/plain', type);
        event.dataTransfer.effectAllowed = 'copy';
        item.classList.add('is-dragging');
    });

    document.addEventListener('dragend', (event) => {
        const item = event.target.closest('[data-canvas-node-type]');
        if (item) {
            item.classList.remove('is-dragging');
        }
    });
}

function bindLegacyExportHandlers() {
    if (window.__neuronaiJsonIoBound) {
        return;
    }
    window.__neuronaiJsonIoBound = true;

    document.addEventListener('click', (event) => {
        const importBtn = event.target.closest('[data-workflow-import-json]');
        if (importBtn && !importBtn.disabled) {
            openImportModal();
        }
    });
}

export function mountWorkflowEditor(rootEl, config = {}) {
    if (!rootEl) {
        return null;
    }

    window.__NEURONAI_CANVAS_CONFIG = { ...config, savedGraph: config.graph };
    window.__workflowGraphDirty = false;

    let root = editorRoots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        editorRoots.set(rootEl, root);
    }

    root.render(<WorkflowEditorShell config={config} />);

    bindPaletteDrag();
    bindSaveHandler();
    bindLegacyExportHandlers();

    window.addEventListener('canvas-inspector-flush', () => {
        captureInspectorFlushUpdate();
    });

    return root;
}

window.mountWorkflowEditor = mountWorkflowEditor;
window.saveGraphBeforeRun = saveGraphBeforeRun;
window.bootstrapWorkflowCanvas = () => {
    const root = document.getElementById('workflow-editor-root');
    if (root && !root.dataset.mounted) {
        mountWorkflowEditor(root, window.__NEURONAI_CANVAS_CONFIG);
        root.dataset.mounted = '1';
    }
};

window.bootstrapWorkflowCanvas();
