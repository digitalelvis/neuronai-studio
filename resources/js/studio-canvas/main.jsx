import { createRoot } from 'react-dom/client';
import WorkflowCanvas from './WorkflowCanvas';

const roots = new WeakMap();
let workflowChatMounted = false;

export function mountWorkflowCanvas(rootEl, config = {}) {
    if (!rootEl) return null;

    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }

    root.render(
        <WorkflowCanvas
            graph={config.graph}
            nodeTypesMeta={config.nodeTypes || {}}
            onGraphChange={config.onGraphChange}
        />,
    );

    return root;
}

function bindPaletteDrag() {
    if (window.__neuronaiCanvasDragBound) return;
    window.__neuronaiCanvasDragBound = true;

    document.addEventListener('dragstart', (event) => {
        const item = event.target.closest('[data-canvas-node-type]');
        if (!item) return;

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
            node.id === pendingUpdate.id
                ? { ...node, data: { ...(node.data || {}), ...pendingUpdate.data } }
                : node,
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
    if (!graph) return;

    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;
    if (wireId && window.Livewire) {
        const component = window.Livewire.find(wireId);
        if (component) {
            component.call('saveGraph', graph);
        }
    }
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
    if (window.__neuronaiCanvasSaveBound) return;
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
    const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;

    if (!graph || !wireId || !window.Livewire) {
        return false;
    }

    const component = window.Livewire.find(wireId);
    if (!component) {
        return false;
    }

    await component.call('saveGraph', graph);
    return true;
}

function bindOpenTestHandler() {
    if (window.__neuronaiCanvasOpenTestBound) return;
    window.__neuronaiCanvasOpenTestBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-workflow-open-test]');
        if (!button || button.disabled) return;

        window.dispatchEvent(new CustomEvent('workflow-open-test'));
    });
}

function bootstrapWorkflowChat() {
    const config = window.__NEURONAI_CANVAS_CONFIG;
    const root = document.getElementById('studio-chat-workflow-root');

    if (!config?.streamUrl || !root || !window.mountStudioChat) {
        return;
    }

    if (workflowChatMounted && root.dataset.mounted === '1') {
        return;
    }

    window.mountStudioChat(root, {
        mode: 'workflow',
        entityId: config.workflowId,
        streamUrl: config.streamUrl,
        resumeUrlTemplate: config.resumeUrlTemplate,
        uploadUrl: config.uploadUrl,
        onBeforeRun: saveGraphBeforeRun,
        syncCanvas: true,
    });

    root.dataset.mounted = '1';
    workflowChatMounted = true;
}

function bootstrapWorkflowCanvas() {
    const config = window.__NEURONAI_CANVAS_CONFIG;
    if (!config) return;

    const root = document.getElementById('workflow-canvas-root');
    if (!root || root.dataset.mounted === '1') return;

    try {
        mountWorkflowCanvas(root, {
            graph: config.graph,
            nodeTypes: config.nodeTypes || {},
            onGraphChange(graph) {
                window.__workflowGraph = graph;
            },
        });
        root.dataset.mounted = '1';
        bindPaletteDrag();
        bindSaveHandler();
        bindOpenTestHandler();
    } catch (error) {
        console.error('[NeuronAI Studio] Failed to mount workflow canvas:', error);
    }
}

window.mountWorkflowCanvas = mountWorkflowCanvas;
window.bootstrapWorkflowCanvas = bootstrapWorkflowCanvas;
window.bootstrapWorkflowChat = bootstrapWorkflowChat;
window.saveGraphBeforeRun = saveGraphBeforeRun;

window.addEventListener('workflow-open-test', () => {
    bootstrapWorkflowChat();
});

bootstrapWorkflowCanvas();
