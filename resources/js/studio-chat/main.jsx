import { createRoot } from 'react-dom/client';
import StudioTestHarness from './StudioTestHarness';
import { AgentSessionAdapter } from './adapters/AgentSessionAdapter';
import { WorkflowSessionAdapter } from './adapters/WorkflowSessionAdapter';
import '../../css/globals.css';

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
    const embedded = config.embedded === true;

    root.render(
        <StudioTestHarness
            adapter={adapter}
            mode={config.mode ?? 'agent'}
            entityId={config.entityId}
            enableAttachments={Boolean(config.uploadUrl)}
            initialContext={config.initialContext ?? {}}
            onRunCompleted={config.onRunCompleted}
            agentMeta={config.agentMeta ?? null}
            embedded={embedded}
        />,
    );

    return root;
}

window.mountStudioChat = mountStudioChat;
