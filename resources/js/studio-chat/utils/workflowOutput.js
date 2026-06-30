const INTERNAL_KEYS = ['__steps', '__current_node_id', '__workflow_trace_id'];
const CONTENT_NODE_TYPES = new Set(['llm', 'agent', 'human', 'tool', 'mcp', 'rag']);
const SKIP_NODE_TYPES = new Set(['set_state', 'condition', 'stop', 'delay']);
const METADATA_OUTPUT_KEYS = new Set(['input', 'attachments', '__studio_thread_id', '__studio_current_step', '__workflowId']);
const TRUNCATE_LENGTH = 500;

function isInternalKey(key) {
    return key.startsWith('__');
}

function formatValue(value) {
    if (value == null) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'object') {
        try {
            return JSON.stringify(value, null, 2);
        } catch {
            return String(value);
        }
    }

    return String(value);
}

function formatAttachmentSummary(attachments) {
    if (!Array.isArray(attachments) || attachments.length === 0) {
        return '';
    }

    const names = attachments
        .map((attachment) => attachment?.name || attachment?.storage_key || 'attachment')
        .filter(Boolean);

    if (names.length === 0) {
        return `${attachments.length} attachment(s)`;
    }

    return `Attached: ${names.join(', ')}`;
}

function resolveUserMessage(output, userMessage = '') {
    const message = userMessage || output?.input || '';
    if (message) {
        return message;
    }

    return formatAttachmentSummary(output?.attachments);
}

function diffStateSnapshots(previous, current, userMessage) {
    const entries = [];
    const prev = previous ?? {};

    for (const [key, value] of Object.entries(current ?? {})) {
        if (isInternalKey(key)) {
            continue;
        }

        const formatted = formatValue(value);
        if (!formatted) {
            continue;
        }

        if (formatted === userMessage) {
            continue;
        }

        if (prev[key] === value) {
            continue;
        }

        entries.push({ key, content: formatted });
    }

    return entries;
}

export function filterWorkflowOutput(output) {
    if (!output || typeof output !== 'object') {
        return {};
    }

    const filtered = { ...output };
    for (const key of INTERNAL_KEYS) {
        delete filtered[key];
    }

    return filtered;
}

export function formatWorkflowData(output, compact = false) {
    if (!output) {
        return 'Workflow completed.';
    }

    try {
        const filtered = filterWorkflowOutput(output);
        return compact ? JSON.stringify(filtered) : JSON.stringify(filtered, null, 2);
    } catch {
        return String(output);
    }
}

export function buildWorkflowOutputFallback(output, userMessage = '') {
    if (!output || typeof output !== 'object') {
        return [];
    }

    const thread = [];
    const message = resolveUserMessage(output, userMessage);

    if (message) {
        thread.push({
            nodeId: '__start__',
            nodeType: 'start',
            label: '__start__',
            content: message,
        });
    }

    for (const [key, value] of Object.entries(output)) {
        if (isInternalKey(key) || METADATA_OUTPUT_KEYS.has(key)) {
            continue;
        }

        const formatted = formatValue(value);
        if (!formatted || formatted === message) {
            continue;
        }

        thread.push({
            nodeId: key,
            nodeType: 'output',
            label: key,
            content: formatted,
            key,
        });
    }

    return thread;
}

export function buildWorkflowPrettyThread(output, userMessage = '') {
    const thread = [];
    const message = resolveUserMessage(output, userMessage);

    if (message) {
        thread.push({
            nodeId: '__start__',
            nodeType: 'start',
            label: '__start__',
            content: message,
        });
    }

    const steps = Array.isArray(output?.__steps) ? output.__steps : [];
    let previousSnapshot = {};

    for (const step of steps) {
        const nodeType = step.node_type ?? 'unknown';
        const nodeId = step.node_id ?? nodeType;
        const snapshot = step.state_snapshot ?? {};

        if (nodeType === 'start') {
            previousSnapshot = snapshot;
            continue;
        }

        if (SKIP_NODE_TYPES.has(nodeType)) {
            previousSnapshot = snapshot;
            continue;
        }

        if (CONTENT_NODE_TYPES.has(nodeType)) {
            const diffs = diffStateSnapshots(previousSnapshot, snapshot, message);

            for (const diff of diffs) {
                thread.push({
                    nodeId,
                    nodeType,
                    label: nodeId,
                    content: diff.content,
                    key: diff.key,
                    durationMs: step.duration_ms ?? null,
                });
            }
        }

        previousSnapshot = snapshot;
    }

    const hasContentEntries = thread.some((entry) => entry.nodeType !== 'start');

    if (hasContentEntries) {
        return thread;
    }

    const fallback = buildWorkflowOutputFallback(output, userMessage);

    if (thread.length === 1 && thread[0]?.nodeType === 'start' && fallback.length > 1) {
        return [thread[0], ...fallback.slice(1)];
    }

    return fallback.length ? fallback : thread;
}

export function buildPartialWorkflowThread(stepEvents = [], userMessage = '', currentNodeId = null) {
    const thread = [];

    if (userMessage) {
        thread.push({
            nodeId: '__start__',
            nodeType: 'start',
            label: '__start__',
            content: userMessage,
        });
    }

    for (const step of stepEvents) {
        if (SKIP_NODE_TYPES.has(step.nodeType)) {
            continue;
        }

        thread.push({
            nodeId: step.nodeId,
            nodeType: step.nodeType,
            label: step.nodeId,
            content: null,
            durationMs: step.durationMs ?? null,
            pending: true,
        });
    }

    if (currentNodeId) {
        thread.push({
            nodeId: currentNodeId,
            nodeType: 'running',
            label: currentNodeId,
            content: null,
            pending: true,
            running: true,
        });
    }

    return thread;
}

export { TRUNCATE_LENGTH };
