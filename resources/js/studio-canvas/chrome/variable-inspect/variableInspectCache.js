import { fetchTrace, fetchTraces, resolveTraceUrl } from '../../../studio-traces/traceApi';
import { buildInspectTree, extractCompletedNodeIds } from './buildInspectTree';

/**
 * @typedef {{
 *   workflowId: string|null,
 *   runId: string|number|null,
 *   tree: import('./buildInspectTree').InspectNodeGroup[],
 *   completedNodeIds: string[],
 *   loading: boolean,
 *   error: string|null,
 * }} VariableInspectState
 */

/** @type {VariableInspectState} */
let state = emptyState();

/** @type {Set<(next: VariableInspectState) => void>} */
const listeners = new Set();

function emptyState() {
    return {
        workflowId: null,
        runId: null,
        tree: [],
        completedNodeIds: [],
        loading: false,
        error: null,
    };
}

function emitWindow(name, detail) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(name, { detail }));
}

function setState(partial) {
    state = { ...state, ...partial };
    listeners.forEach((listener) => listener(state));
    return state;
}

export function getVariableInspectState() {
    return state;
}

/**
 * @param {(next: VariableInspectState) => void} listener
 * @returns {() => void}
 */
export function subscribeVariableInspect(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

/**
 * @param {string|null|undefined} workflowId
 * @returns {string}
 */
export function inspectPrefsStorageKey(workflowId) {
    return `neuronai-studio:workflow:${workflowId || 'unknown'}:variable-inspect`;
}

/**
 * @param {string|null|undefined} workflowId
 * @returns {{ open?: boolean, height?: number }}
 */
export function loadInspectPanelPrefs(workflowId) {
    if (typeof localStorage === 'undefined' || !workflowId) {
        return {};
    }

    try {
        const raw = localStorage.getItem(inspectPrefsStorageKey(workflowId));
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

/**
 * @param {string|null|undefined} workflowId
 * @param {{ open?: boolean, height?: number }} prefs
 */
export function saveInspectPanelPrefs(workflowId, prefs) {
    if (typeof localStorage === 'undefined' || !workflowId) {
        return;
    }

    try {
        const current = loadInspectPanelPrefs(workflowId);
        localStorage.setItem(
            inspectPrefsStorageKey(workflowId),
            JSON.stringify({ ...current, ...prefs }),
        );
    } catch {
        // Ignore quota / private mode errors.
    }
}

/**
 * @param {{
 *   workflowId?: string|null,
 *   output?: Record<string, unknown>|null,
 *   steps?: unknown[]|null,
 *   runId?: string|number|null,
 * }} payload
 */
export function applyVariableInspectCache(payload = {}) {
    const workflowId = payload.workflowId ?? state.workflowId;
    const source = { output: payload.output ?? null, steps: payload.steps ?? null };
    const tree = buildInspectTree(source);
    const completedNodeIds = extractCompletedNodeIds(source);

    const next = setState({
        workflowId,
        runId: payload.runId ?? state.runId,
        tree,
        completedNodeIds,
        loading: false,
        error: null,
    });

    emitWindow('variable-inspect-updated', next);
    return next;
}

export function resetVariableInspectCache() {
    const workflowId = state.workflowId;
    const next = setState({
        ...emptyState(),
        workflowId,
    });

    emitWindow('variable-inspect-reset', next);
    return next;
}

/**
 * Load the latest completed StudioRun for a workflow and populate the inspect cache.
 *
 * @param {{
 *   workflowId: string,
 *   tracesIndexUrl: string,
 *   traceShowJsonUrlTemplate: string,
 * }} options
 */
export async function hydrateVariableInspectFromTraces({
    workflowId,
    tracesIndexUrl,
    traceShowJsonUrlTemplate,
}) {
    if (!workflowId || !tracesIndexUrl || !traceShowJsonUrlTemplate) {
        return state;
    }

    setState({ workflowId, loading: true, error: null });

    try {
        const list = await fetchTraces(tracesIndexUrl, { page: 1, perPage: 25 });
        const runs = Array.isArray(list?.data) ? list.data : [];
        const completed = runs.find((run) => run?.status === 'completed');

        if (!completed?.id) {
            const next = setState({
                workflowId,
                runId: null,
                tree: [],
                completedNodeIds: [],
                loading: false,
                error: null,
            });
            return next;
        }

        const detail = await fetchTrace(resolveTraceUrl(traceShowJsonUrlTemplate, completed.id));
        const output = detail?.trace?.output && typeof detail.trace.output === 'object'
            ? detail.trace.output
            : null;
        const steps = Array.isArray(detail?.steps) ? detail.steps : null;

        return applyVariableInspectCache({
            workflowId,
            runId: completed.id,
            output,
            steps,
        });
    } catch (error) {
        return setState({
            workflowId,
            loading: false,
            error: error instanceof Error ? error.message : 'Failed to load cached variables.',
        });
    }
}

/**
 * Wire global CustomEvents used by Playground / shell.
 */
export function bindVariableInspectEvents() {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const onCache = (event) => {
        const detail = event.detail || {};
        applyVariableInspectCache({
            workflowId: detail.workflowId,
            output: detail.output,
            steps: detail.steps,
            runId: detail.runId ?? detail.trace_id ?? null,
        });
    };

    const onReset = () => {
        resetVariableInspectCache();
    };

    window.addEventListener('workflow-variable-cache', onCache);
    window.addEventListener('workflow-variable-cache-reset', onReset);

    return () => {
        window.removeEventListener('workflow-variable-cache', onCache);
        window.removeEventListener('workflow-variable-cache-reset', onReset);
    };
}
