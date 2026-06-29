import { createRoot } from 'react-dom/client';
import AgentForm from './AgentForm';
import ToolBuilder from './ToolBuilder';
import TraceDetailViewer from '../studio-traces/TraceDetailViewer';
import '../../css/globals.css';

const roots = new WeakMap();

export function mountAgentForm(rootEl, config = {}) {
    if (!rootEl) return null;
    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }
    root.render(<AgentForm config={config} />);
    return root;
}

export function mountToolBuilder(rootEl, config = {}) {
    if (!rootEl) return null;
    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }
    root.render(<ToolBuilder config={config} />);
    return root;
}

export function mountTraceDetailViewer(rootEl, config = {}) {
    if (!rootEl) return null;
    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }

    const trace = config.trace ?? {};
    root.render(
        <TraceDetailViewer
            variant="page"
            trace={{
                id: trace.id,
                status: trace.status,
                workflowName: trace.workflowName,
                errorMessage: trace.errorMessage,
                input: trace.input,
                output: trace.output,
                durationMs: trace.durationMs,
            }}
            steps={config.steps ?? []}
            traceShowUrl={config.traceShowUrl}
        />,
    );
    return root;
}

/** @deprecated Use mountTraceDetailViewer */
export const mountRunDetailViewer = mountTraceDetailViewer;

window.mountAgentForm = mountAgentForm;
window.mountToolBuilder = mountToolBuilder;
window.mountTraceDetailViewer = mountTraceDetailViewer;
window.mountRunDetailViewer = mountRunDetailViewer;

document.addEventListener('DOMContentLoaded', () => {
    const agentRoot = document.getElementById('agent-form-root');
    if (agentRoot && window.__NEURONAI_AGENT_FORM_CONFIG) {
        mountAgentForm(agentRoot, window.__NEURONAI_AGENT_FORM_CONFIG);
    }

    const toolRoot = document.getElementById('tool-builder-root');
    if (toolRoot && window.__NEURONAI_TOOL_BUILDER_CONFIG) {
        mountToolBuilder(toolRoot, window.__NEURONAI_TOOL_BUILDER_CONFIG);
    }

    const traceRoot = document.getElementById('trace-detail-root');
    if (traceRoot && window.__NEURONAI_TRACE_DETAIL_CONFIG) {
        mountTraceDetailViewer(traceRoot, window.__NEURONAI_TRACE_DETAIL_CONFIG);
    }

    const legacyRunRoot = document.getElementById('run-detail-root');
    if (legacyRunRoot && window.__NEURONAI_RUN_DETAIL_CONFIG) {
        mountTraceDetailViewer(legacyRunRoot, {
            trace: window.__NEURONAI_RUN_DETAIL_CONFIG.run,
            steps: window.__NEURONAI_RUN_DETAIL_CONFIG.steps,
        });
    }
});
