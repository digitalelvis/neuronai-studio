import { createRoot } from 'react-dom/client';
import WorkflowCanvas from './WorkflowCanvas';

const roots = new WeakMap();

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

function bindSaveHandler() {
    if (window.__neuronaiCanvasSaveBound) return;
    window.__neuronaiCanvasSaveBound = true;

    window.addEventListener('workflow-canvas-save', () => {
        const graph = window.__workflowGraphExport?.() ?? window.__workflowGraph;
        if (!graph) return;

        const wireId = window.__NEURONAI_CANVAS_CONFIG?.wireId;
        if (wireId && window.Livewire) {
            const component = window.Livewire.find(wireId);
            if (component) {
                component.call('saveGraph', graph);
            }
        }
    });
}

async function saveGraphBeforeRun() {
    const graph = window.__workflowGraphExport?.() ?? window.__workflowGraph;
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

function bindRunTestHandler() {
    if (window.__neuronaiCanvasRunBound) return;
    window.__neuronaiCanvasRunBound = true;

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-workflow-run-test]');
        if (!button || button.disabled) return;

        const config = window.__NEURONAI_CANVAS_CONFIG;
        if (!config?.streamUrl) {
            window.alert('Save the workflow before running a test.');
            return;
        }

        button.disabled = true;

        try {
            await saveGraphBeforeRun();
            window.dispatchEvent(new CustomEvent('canvas-run-start'));

            const url = new URL(config.streamUrl, window.location.origin);
            url.searchParams.set('input', 'Hello from workflow test');

            const source = new EventSource(url.toString());

            source.addEventListener('step_started', (message) => {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: 'step_started', ...JSON.parse(message.data) },
                    }),
                );
            });

            source.addEventListener('step_completed', (message) => {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: 'step_completed', ...JSON.parse(message.data) },
                    }),
                );
            });

            source.addEventListener('run_completed', (message) => {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: 'run_completed', ...JSON.parse(message.data) },
                    }),
                );
                source.close();
                button.disabled = false;
            });

            source.addEventListener('run_failed', (message) => {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: 'run_failed', ...JSON.parse(message.data) },
                    }),
                );
                source.close();
                button.disabled = false;
            });

            source.onerror = () => {
                source.close();
                button.disabled = false;
            };
        } catch (error) {
            console.error('[NeuronAI Studio] Workflow run failed:', error);
            button.disabled = false;
        }
    });
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
        bindRunTestHandler();
    } catch (error) {
        console.error('[NeuronAI Studio] Failed to mount workflow canvas:', error);
    }
}

window.mountWorkflowCanvas = mountWorkflowCanvas;
window.bootstrapWorkflowCanvas = bootstrapWorkflowCanvas;

bootstrapWorkflowCanvas();
