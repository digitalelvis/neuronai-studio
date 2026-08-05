/**
 * Build a Dify-style variable inspect tree from workflow run output / trace steps.
 *
 * @typedef {{ key: string, type: string, value: unknown }} InspectVariable
 * @typedef {{ nodeId: string, nodeType: string, label: string, variables: InspectVariable[] }} InspectNodeGroup
 */

const METADATA_SKIP_KEYS = new Set([
    'attachments',
    '__studio_thread_id',
    '__studio_current_step',
    '__workflowId',
]);

/**
 * @param {unknown} value
 * @returns {string}
 */
export function inferValueType(value) {
    if (value === null || value === undefined) {
        return 'null';
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return 'array';
        }

        const itemType = inferValueType(value[0]);
        if (itemType === 'object' || itemType === 'string' || itemType === 'number' || itemType === 'boolean') {
            return `array[${itemType}]`;
        }

        return 'array';
    }

    if (typeof value === 'object') {
        return 'object';
    }

    return typeof value;
}

/**
 * @param {unknown} a
 * @param {unknown} b
 * @returns {boolean}
 */
function valuesEqual(a, b) {
    if (a === b) {
        return true;
    }

    if (a == null || b == null) {
        return a === b;
    }

    if (typeof a !== 'object' || typeof b !== 'object') {
        return false;
    }

    try {
        return JSON.stringify(a) === JSON.stringify(b);
    } catch {
        return false;
    }
}

/**
 * @param {string} key
 * @returns {boolean}
 */
function shouldSkipKey(key) {
    return key.startsWith('__') || METADATA_SKIP_KEYS.has(key);
}

/**
 * @param {Record<string, unknown>|null|undefined} previous
 * @param {Record<string, unknown>|null|undefined} current
 * @returns {InspectVariable[]}
 */
function diffSnapshotVariables(previous, current) {
    /** @type {InspectVariable[]} */
    const variables = [];
    const prev = previous && typeof previous === 'object' ? previous : {};

    for (const [key, value] of Object.entries(current ?? {})) {
        if (shouldSkipKey(key)) {
            continue;
        }

        if (valuesEqual(prev[key], value)) {
            continue;
        }

        variables.push({
            key,
            type: inferValueType(value),
            value,
        });
    }

    return variables;
}

/**
 * @param {Record<string, unknown>|null|undefined} snapshot
 * @returns {InspectVariable[]}
 */
function startVariablesFromSnapshot(snapshot) {
    /** @type {InspectVariable[]} */
    const variables = [];

    for (const [key, value] of Object.entries(snapshot ?? {})) {
        if (shouldSkipKey(key)) {
            continue;
        }

        variables.push({
            key,
            type: inferValueType(value),
            value,
        });
    }

    return variables;
}

/**
 * @param {unknown} step
 * @returns {{ nodeId: string, nodeType: string, stateSnapshot: Record<string, unknown> }}
 */
function normalizeStep(step) {
    const raw = step && typeof step === 'object' ? /** @type {Record<string, unknown>} */ (step) : {};
    const nodeId = typeof raw.node_id === 'string' ? raw.node_id : String(raw.node_id ?? 'unknown');
    const nodeType = typeof raw.node_type === 'string' ? raw.node_type : String(raw.node_type ?? 'unknown');
    const stateSnapshot =
        raw.state_snapshot && typeof raw.state_snapshot === 'object' && !Array.isArray(raw.state_snapshot)
            ? /** @type {Record<string, unknown>} */ (raw.state_snapshot)
            : {};

    return { nodeId, nodeType, stateSnapshot };
}

/**
 * @param {{ output?: Record<string, unknown>|null, steps?: unknown[]|null }} [source]
 * @returns {InspectNodeGroup[]}
 */
export function buildInspectTree(source = {}) {
    const output = source.output && typeof source.output === 'object' ? source.output : null;
    const stepList =
        Array.isArray(source.steps) && source.steps.length > 0
            ? source.steps
            : Array.isArray(output?.__steps)
              ? output.__steps
              : [];

    /** @type {InspectNodeGroup[]} */
    const groups = [];
    /** @type {Record<string, unknown>} */
    let previousSnapshot = {};
    let sawStart = false;

    for (const rawStep of stepList) {
        const step = normalizeStep(rawStep);

        if (step.nodeType === 'start' || step.nodeId === '__start__' || step.nodeId === 'start') {
            const variables = startVariablesFromSnapshot(step.stateSnapshot);
            if (variables.length > 0) {
                groups.push({
                    nodeId: step.nodeId === 'start' ? 'start' : step.nodeId,
                    nodeType: 'start',
                    label: 'START',
                    variables,
                });
            }
            previousSnapshot = step.stateSnapshot;
            sawStart = true;
            continue;
        }

        const variables = diffSnapshotVariables(previousSnapshot, step.stateSnapshot);
        if (variables.length > 0) {
            groups.push({
                nodeId: step.nodeId,
                nodeType: step.nodeType,
                label: step.nodeId,
                variables,
            });
        }

        previousSnapshot = step.stateSnapshot;
    }

    if (!sawStart && output) {
        /** @type {InspectVariable[]} */
        const startVars = [];
        if (typeof output.input === 'string' || output.input != null) {
            startVars.push({
                key: 'input',
                type: inferValueType(output.input),
                value: output.input,
            });
        }

        if (startVars.length > 0) {
            groups.unshift({
                nodeId: 'start',
                nodeType: 'start',
                label: 'START',
                variables: startVars,
            });
        }
    }

    // Newest execution first (Dify lists terminal nodes toward the top).
    return groups.reverse();
}

/**
 * @param {{ output?: Record<string, unknown>|null, steps?: unknown[]|null }} [source]
 * @returns {string[]}
 */
export function extractCompletedNodeIds(source = {}) {
    const output = source.output && typeof source.output === 'object' ? source.output : null;
    const stepList =
        Array.isArray(source.steps) && source.steps.length > 0
            ? source.steps
            : Array.isArray(output?.__steps)
              ? output.__steps
              : [];

    const ids = [];
    const seen = new Set();

    for (const rawStep of stepList) {
        const step = normalizeStep(rawStep);
        if (!step.nodeId || seen.has(step.nodeId)) {
            continue;
        }

        seen.add(step.nodeId);
        ids.push(step.nodeId);
    }

    return ids;
}

/**
 * @param {unknown} value
 * @returns {string}
 */
export function formatInspectValue(value) {
    if (value == null) {
        return String(value);
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if ((trimmed.startsWith('{') || trimmed.startsWith('[')) && trimmed.length > 1) {
            try {
                return JSON.stringify(JSON.parse(trimmed), null, 2);
            } catch {
                return value;
            }
        }

        return value;
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
}
