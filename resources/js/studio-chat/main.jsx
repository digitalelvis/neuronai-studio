import { createRoot } from 'react-dom/client';
import StudioChat from './StudioChat';
import { AgentSessionAdapter } from './adapters/AgentSessionAdapter';
import { WorkflowSessionAdapter } from './adapters/WorkflowSessionAdapter';
import './chat.css';

const roots = new WeakMap();

function createAdapter(config) {
    if (config.mode === 'workflow') {
        return new WorkflowSessionAdapter({
            streamUrl: config.streamUrl,
            resumeUrlTemplate: config.resumeUrlTemplate,
            onBeforeRun: config.onBeforeRun,
            syncCanvas: config.syncCanvas !== false,
        });
    }

    return new AgentSessionAdapter({
        streamUrl: config.streamUrl,
        uploadUrl: config.uploadUrl,
    });
}

export function mountStudioChat(rootEl, config = {}) {
    if (!rootEl) {
        return null;
    }

    let root = roots.get(rootEl);
    if (!root) {
        root = createRoot(rootEl);
        roots.set(rootEl, root);
    }

    const adapter = createAdapter(config);

    root.render(
        <StudioChat
            adapter={adapter}
            mode={config.mode ?? 'agent'}
            entityId={config.entityId}
            enableAttachments={Boolean(config.uploadUrl)}
            showPlayground={config.showPlayground !== false}
            initialContext={config.initialContext ?? {}}
            onRunCompleted={config.onRunCompleted}
        />,
    );

    return root;
}

window.mountStudioChat = mountStudioChat;
