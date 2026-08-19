import { useEffect, useMemo, useState } from 'react';

/** @typedef {'start'|'initial'|'node'|'system'} StateVariableGroup */

/**
 * @typedef {{
 *   key: string,
 *   label: string,
 *   type: string,
 *   group: StateVariableGroup,
 *   sourceNodeId?: string,
 *   sourceLabel?: string,
 * }} StateVariable
 */

export const SYSTEM_STATE_VARIABLES = /** @type {const} */ ([
    { key: '__studio_thread_id', label: '__studio_thread_id', type: 'string' },
    { key: '__studio_run_id', label: '__studio_run_id', type: 'string' },
    { key: '__studio_trace_id', label: '__studio_trace_id', type: 'string' },
    { key: '__studio_owner_type', label: '__studio_owner_type', type: 'string' },
    { key: '__studio_owner_id', label: '__studio_owner_id', type: 'string' },
    { key: '__studio_now', label: '__studio_now', type: 'string' },
    { key: '__studio_timezone', label: '__studio_timezone', type: 'string' },
    { key: '__studio_locale', label: '__studio_locale', type: 'string' },
    { key: '__workflow_trace_id', label: '__workflow_trace_id', type: 'string' },
    { key: '__workflow_nesting_depth', label: '__workflow_nesting_depth', type: 'number' },
]);

const DEFAULT_OUTPUT_KEYS = {
    agent: 'agent_response',
    llm: 'llm_response',
    intent_classifier: 'intent',
    human: 'human_response',
    tool: 'tool_result',
    mcp: 'mcp_result',
    rag: 'rag_context',
    invoke: 'invoke_result',
    run_workflow: 'child_output',
    join: 'parallel_results',
};

/** @type {{ nodes: unknown[], edges: unknown[] }} */
let graphSnapshot = { nodes: [], edges: [] };

/** @type {Set<(snapshot: { nodes: unknown[], edges: unknown[] }) => void>} */
const graphListeners = new Set();

/** @type {Record<string, unknown>} */
let initialSnapshot = {};

/** @type {Set<(snapshot: Record<string, unknown>) => void>} */
const initialListeners = new Set();

/**
 * Keep a live copy of the canvas graph for inspectors outside ReactFlowProvider
 * (e.g. NodeInspectorSidebar).
 *
 * @param {unknown[]} nodes
 * @param {unknown[]} [edges]
 */
export function setStateVariableGraphSnapshot(nodes, edges = []) {
    graphSnapshot = {
        nodes: Array.isArray(nodes) ? nodes : [],
        edges: Array.isArray(edges) ? edges : [],
    };
    graphListeners.forEach((listener) => listener(graphSnapshot));
}

export function getStateVariableGraphSnapshot() {
    return graphSnapshot;
}

/**
 * @param {(snapshot: { nodes: unknown[], edges: unknown[] }) => void} listener
 * @returns {() => void}
 */
export function subscribeStateVariableGraphSnapshot(listener) {
    graphListeners.add(listener);
    return () => graphListeners.delete(listener);
}

/**
 * Keep a live copy of playground Initial State JSON for the state variable catalog.
 *
 * @param {unknown} value
 */
export function setStateVariableInitialSnapshot(value) {
    initialSnapshot =
        value && typeof value === 'object' && !Array.isArray(value)
            ? /** @type {Record<string, unknown>} */ (value)
            : {};
    initialListeners.forEach((listener) => listener(initialSnapshot));
}

export function getStateVariableInitialSnapshot() {
    return initialSnapshot;
}

/**
 * @param {(snapshot: Record<string, unknown>) => void} listener
 * @returns {() => void}
 */
export function subscribeStateVariableInitialSnapshot(listener) {
    initialListeners.add(listener);
    return () => initialListeners.delete(listener);
}

/**
 * @param {unknown} value
 * @returns {'string'|'number'|'boolean'|'array'|'object'}
 */
export function inferStateValueType(value) {
    if (Array.isArray(value)) {
        return 'array';
    }
    if (value === null) {
        return 'object';
    }

    const kind = typeof value;
    if (kind === 'string' || kind === 'number' || kind === 'boolean') {
        return kind;
    }
    if (kind === 'object') {
        return 'object';
    }

    return 'string';
}

/**
 * Flatten nested plain objects into dot paths (`lead.tier`) matching runtime `data_get`.
 * Arrays and scalars become leaf entries; nested objects emit both the object key and children.
 *
 * @param {unknown} value
 * @param {string} [prefix]
 * @returns {Array<{ key: string, type: string }>}
 */
export function flattenInitialStateKeys(value, prefix = '') {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return [];
    }

    /** @type {Array<{ key: string, type: string }>} */
    const entries = [];
    const record = /** @type {Record<string, unknown>} */ (value);

    for (const [rawKey, nested] of Object.entries(record)) {
        if (typeof rawKey !== 'string' || rawKey.trim() === '') {
            continue;
        }

        const key = prefix ? `${prefix}.${rawKey}` : rawKey;
        const type = inferStateValueType(nested);
        entries.push({ key, type });

        if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
            entries.push(...flattenInitialStateKeys(nested, key));
        }
    }

    return entries;
}

/** @param {string} key */
export function toTemplate(key) {
    return `{{${key}}}`;
}

/**
 * Strip a single `{{key}}` wrapper; leave raw keys untouched.
 * @param {unknown} value
 */
export function stripTemplate(value) {
    if (typeof value !== 'string') {
        return '';
    }

    const trimmed = value.trim();
    const match = trimmed.match(/^\{\{\s*([\w.]+)\s*\}\}$/);
    return match ? match[1] : trimmed;
}

/**
 * Normalize user input into a raw state key (strip `{{key}}`, validate chars).
 * Returns empty string when invalid.
 * @param {unknown} input
 * @returns {string}
 */
export function normalizeStateKey(input) {
    if (typeof input !== 'string') {
        return '';
    }

    const key = stripTemplate(input.trim());
    if (key === '' || !/^[\w.]+$/.test(key)) {
        return '';
    }

    return key;
}

/**
 * @param {string} text
 * @returns {Array<{ key: string, start: number, end: number, raw: string }>}
 */
export function parseTemplateRefs(text) {
    if (typeof text !== 'string' || text === '') {
        return [];
    }

    const refs = [];
    const re = /\{\{\s*([\w.]+)\s*\}\}/g;
    let match;

    while ((match = re.exec(text)) !== null) {
        refs.push({
            key: match[1],
            start: match.index,
            end: match.index + match[0].length,
            raw: match[0],
        });
    }

    return refs;
}

/**
 * @param {string} text
 * @returns {Array<{ type: 'text'|'var', value?: string, key?: string }>}
 */
export function parseTemplateSegments(text) {
    const source = typeof text === 'string' ? text : '';
    const refs = parseTemplateRefs(source);

    if (refs.length === 0) {
        return source === '' ? [] : [{ type: 'text', value: source }];
    }

    /** @type {Array<{ type: 'text'|'var', value?: string, key?: string }>} */
    const segments = [];
    let cursor = 0;

    for (const ref of refs) {
        if (ref.start > cursor) {
            segments.push({ type: 'text', value: source.slice(cursor, ref.start) });
        }
        segments.push({ type: 'var', key: ref.key });
        cursor = ref.end;
    }

    if (cursor < source.length) {
        segments.push({ type: 'text', value: source.slice(cursor) });
    }

    return segments;
}

/**
 * @param {Array<{ type: 'text'|'var', value?: string, key?: string }>} segments
 */
export function serializeTemplateSegments(segments) {
    if (!Array.isArray(segments)) {
        return '';
    }

    return segments
        .map((segment) => {
            if (segment.type === 'var' && segment.key) {
                return toTemplate(segment.key);
            }
            return segment.value ?? '';
        })
        .join('');
}

/**
 * @param {Record<string, unknown>|null|undefined} node
 */
export function getFlowNodeType(node) {
    if (!node || typeof node !== 'object') {
        return '';
    }

    const data = /** @type {Record<string, unknown>} */ (node).data;
    if (data && typeof data === 'object' && !Array.isArray(data)) {
        const nested = /** @type {Record<string, unknown>} */ (data);
        if (typeof nested.nodeType === 'string' && nested.nodeType !== '') {
            return nested.nodeType;
        }
    }

    return typeof node.type === 'string' ? node.type : '';
}

/**
 * @param {Record<string, unknown>|null|undefined} node
 */
export function getFlowNodeConfig(node) {
    if (!node || typeof node !== 'object') {
        return {};
    }

    const data = node.data;
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        return {};
    }

    const nested = /** @type {Record<string, unknown>} */ (data);
    if (nested.config && typeof nested.config === 'object' && !Array.isArray(nested.config)) {
        return /** @type {Record<string, unknown>} */ (nested.config);
    }

    // Package-graph shape (data is the config bag).
    return nested;
}

/**
 * @param {Record<string, unknown>|null|undefined} node
 */
export function getFlowNodeLabel(node) {
    if (!node || typeof node !== 'object') {
        return 'Node';
    }

    if (typeof node.title === 'string' && node.title.trim() !== '') {
        return node.title.trim();
    }

    const data = node.data;
    if (data && typeof data === 'object' && !Array.isArray(data)) {
        const nested = /** @type {Record<string, unknown>} */ (data);
        if (typeof nested.title === 'string' && nested.title.trim() !== '') {
            return nested.title.trim();
        }

        if (typeof nested.label === 'string' && nested.label.trim() !== '') {
            return nested.label.trim();
        }
    }

    const type = getFlowNodeType(node);
    return type || (typeof node.id === 'string' ? node.id : 'Node');
}

/**
 * @param {string} nodeType
 * @param {Record<string, unknown>} config
 */
export function resolveOutputKey(nodeType, config = {}) {
    if (typeof config.output_key === 'string' && config.output_key.trim() !== '') {
        return config.output_key.trim();
    }

    return DEFAULT_OUTPUT_KEYS[nodeType] ?? null;
}

/**
 * @param {unknown[]} nodes
 * @param {unknown[]} [_edges]
 * @param {string|null|undefined} [currentNodeId]
 * @param {Record<string, unknown>|null|undefined} [initialState]
 * @returns {StateVariable[]}
 */
export function collectAvailableStateVariables(
    nodes = [],
    _edges = [],
    currentNodeId = null,
    initialState = null,
) {
    /** @type {StateVariable[]} */
    const variables = [
        {
            key: 'input',
            label: 'input',
            type: 'string',
            group: 'start',
            sourceLabel: 'START',
        },
        {
            key: 'attachments',
            label: 'attachments',
            type: 'array',
            group: 'start',
            sourceLabel: 'START',
        },
    ];

    const seenKeys = new Set(['input', 'attachments']);
    const initial =
        initialState && typeof initialState === 'object' && !Array.isArray(initialState)
            ? initialState
            : getStateVariableInitialSnapshot();

    for (const entry of flattenInitialStateKeys(initial)) {
        if (seenKeys.has(entry.key)) {
            continue;
        }
        seenKeys.add(entry.key);
        variables.push({
            key: entry.key,
            label: entry.key,
            type: entry.type,
            group: 'initial',
            sourceLabel: 'INITIAL',
        });
    }

    for (const raw of nodes) {
        if (!raw || typeof raw !== 'object') {
            continue;
        }

        const node = /** @type {Record<string, unknown>} */ (raw);
        const id = typeof node.id === 'string' ? node.id : null;
        if (!id || id === currentNodeId) {
            continue;
        }

        const nodeType = getFlowNodeType(node);
        if (!nodeType || nodeType === 'note' || node.type === 'stickyNote') {
            continue;
        }

        const config = getFlowNodeConfig(node);
        const sourceLabel = getFlowNodeLabel(node);

        /** @type {string[]} */
        const keys = [];

        if (nodeType === 'set_state') {
            if (typeof config.key === 'string' && config.key.trim() !== '') {
                keys.push(config.key.trim());
            }
        } else {
            const outputKey = resolveOutputKey(nodeType, config);
            if (outputKey) {
                keys.push(outputKey);
            }
        }

        for (const key of keys) {
            if (seenKeys.has(key)) {
                continue;
            }
            seenKeys.add(key);
            variables.push({
                key,
                label: key,
                type: 'string',
                group: 'node',
                sourceNodeId: id,
                sourceLabel,
            });
        }
    }

    for (const system of SYSTEM_STATE_VARIABLES) {
        if (seenKeys.has(system.key)) {
            continue;
        }
        seenKeys.add(system.key);
        variables.push({
            key: system.key,
            label: system.label,
            type: system.type,
            group: 'system',
            sourceLabel: 'SYSTEM',
        });
    }

    return variables;
}

/**
 * @param {StateVariable[]} variables
 * @param {string} [query]
 */
export function filterStateVariables(variables, query = '') {
    const needle = query.trim().toLowerCase();
    if (!needle) {
        return variables;
    }

    return variables.filter((variable) => {
        const haystack = [
            variable.key,
            variable.label,
            variable.sourceLabel,
            variable.group,
            variable.type,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return haystack.includes(needle);
    });
}

/**
 * @param {StateVariable[]} variables
 * @returns {Array<{ id: string, title: string, group: StateVariableGroup, variables: StateVariable[] }>}
 */
export function groupStateVariables(variables) {
    /** @type {Map<string, { id: string, title: string, group: StateVariableGroup, variables: StateVariable[] }>} */
    const sections = new Map();

    for (const variable of variables) {
        let id;
        let title;

        if (variable.group === 'start') {
            id = 'start';
            title = 'START';
        } else if (variable.group === 'initial') {
            id = 'initial';
            title = 'INITIAL STATE';
        } else if (variable.group === 'system') {
            id = 'system';
            title = 'SYSTEM';
        } else {
            id = `node:${variable.sourceNodeId || variable.sourceLabel || variable.key}`;
            title = (variable.sourceLabel || 'NODE').toUpperCase();
        }

        if (!sections.has(id)) {
            sections.set(id, {
                id,
                title,
                group: variable.group,
                variables: [],
            });
        }
        sections.get(id).variables.push(variable);
    }

    const order = { start: 0, initial: 1, node: 2, system: 3 };
    return Array.from(sections.values()).sort((a, b) => {
        const aGroup = a.group || a.variables[0]?.group || 'node';
        const bGroup = b.group || b.variables[0]?.group || 'node';
        if (order[aGroup] !== order[bGroup]) {
            return order[aGroup] - order[bGroup];
        }
        return a.title.localeCompare(b.title);
    });
}

/**
 * @param {string|null|undefined} currentNodeId
 * @param {{ nodes?: unknown[], edges?: unknown[], initialState?: Record<string, unknown> }} [options]
 */
export function useAvailableStateVariables(currentNodeId, options = {}) {
    const propNodes = options.nodes;
    const propEdges = options.edges;
    const propInitial = options.initialState;
    const [snapshot, setSnapshot] = useState(() => getStateVariableGraphSnapshot());
    const [initial, setInitial] = useState(() => getStateVariableInitialSnapshot());

    useEffect(() => {
        if (Array.isArray(propNodes)) {
            return undefined;
        }
        return subscribeStateVariableGraphSnapshot(setSnapshot);
    }, [propNodes]);

    useEffect(() => {
        if (propInitial && typeof propInitial === 'object' && !Array.isArray(propInitial)) {
            return undefined;
        }
        return subscribeStateVariableInitialSnapshot(setInitial);
    }, [propInitial]);

    const nodes = Array.isArray(propNodes) ? propNodes : snapshot.nodes;
    const edges = Array.isArray(propEdges) ? propEdges : snapshot.edges;
    const initialState =
        propInitial && typeof propInitial === 'object' && !Array.isArray(propInitial)
            ? propInitial
            : initial;

    return useMemo(
        () => collectAvailableStateVariables(nodes, edges, currentNodeId, initialState),
        [nodes, edges, currentNodeId, initialState],
    );
}
