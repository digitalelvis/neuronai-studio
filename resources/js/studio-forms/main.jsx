import { createRoot } from 'react-dom/client';
import AgentForm from './AgentForm';
import ToolBuilder from './ToolBuilder';
import RunDetailViewer from './RunDetailViewer';
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

export function mountRunDetailViewer(rootEl, config = {}) {
    if (!rootEl) return null;
    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }
    root.render(<RunDetailViewer config={config} />);
    return root;
}

window.mountAgentForm = mountAgentForm;
window.mountToolBuilder = mountToolBuilder;
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

    const runRoot = document.getElementById('run-detail-root');
    if (runRoot && window.__NEURONAI_RUN_DETAIL_CONFIG) {
        mountRunDetailViewer(runRoot, window.__NEURONAI_RUN_DETAIL_CONFIG);
    }
});
