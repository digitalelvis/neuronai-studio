const CATEGORY_COLORS = {
    flow: '#6366f1',
    ai: '#8b5cf6',
    logic: '#f59e0b',
    utilities: '#eab308',
};

function normalizeNodeData(data) {
    if (!data || Array.isArray(data)) {
        return {};
    }

    return data;
}

export function categoryColor(category) {
    return CATEGORY_COLORS[category] || '#6366f1';
}

export function edgeLabelForHandle(handle, targetHandle = 'default') {
    if (targetHandle === 'tools') {
        return 'tools';
    }

    if (handle === 'toolset') {
        return 'toolset';
    }

    if (handle === 'true') {
        return 'true';
    }

    if (handle === 'false') {
        return 'false';
    }

    if (handle === 'continue') {
        return 'continue';
    }

    if (handle === 'exit') {
        return 'exit';
    }

    if (typeof handle === 'string' && handle !== '' && handle !== 'default') {
        return handle;
    }

    return undefined;
}

export function edgeStyleForHandle(handle, targetHandle = 'default') {
    if (targetHandle === 'tools') {
        return { stroke: '#22d3ee', strokeWidth: 2 };
    }

    if (handle === 'toolset') {
        return { stroke: '#f59e0b', strokeWidth: 2 };
    }

    if (handle === 'true') {
        return { stroke: '#22c55e', strokeWidth: 2 };
    }

    if (handle === 'false') {
        return { stroke: '#ef4444', strokeWidth: 2 };
    }

    if (handle === 'continue') {
        return { stroke: '#3b82f6', strokeWidth: 2 };
    }

    if (handle === 'exit') {
        return { stroke: '#a855f7', strokeWidth: 2 };
    }

    return { stroke: '#6366f1', strokeWidth: 2 };
}

export function isToolBindingEdge(edge) {
    return (
        (edge?.targetHandle || 'default') === 'tools' ||
        (edge?.sourceHandle || 'default') === 'toolset'
    );
}

/**
 * ASCII intent/branch id matching backend GraphValidator:
 * /^[a-zA-Z][a-zA-Z0-9_]*$/
 */
export function sanitizeIntentId(value) {
    const slug = String(value || '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');

    if (!slug) {
        return 'intent';
    }

    return /^[a-z]/.test(slug) ? slug : `intent_${slug}`;
}

export function uniqueIntentId(desired, existingIds, excludeId = null) {
    const base = sanitizeIntentId(desired);
    const taken = new Set(
        (existingIds || []).filter((id) => typeof id === 'string' && id !== '' && id !== excludeId),
    );

    if (!taken.has(base)) {
        return base;
    }

    let n = 2;
    let candidate = `${base}_${n}`;
    while (taken.has(candidate)) {
        n += 1;
        candidate = `${base}_${n}`;
    }

    return candidate;
}

export function intentIdsFromConfig(config) {
    if (!config || !Array.isArray(config.intents)) {
        return [];
    }

    return config.intents
        .map((intent) => (intent && typeof intent === 'object' ? String(intent.id || '').trim() : ''))
        .filter((id) => id !== '');
}

export function forkBranchIdsFromConfig(config) {
    if (!config || !Array.isArray(config.branches)) {
        return [];
    }

    return config.branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .map((id) => (typeof id === 'string' ? id.trim() : ''))
        .filter((id) => id !== '');
}

export function switchCaseIdsFromConfig(config) {
    if (!config || !Array.isArray(config.cases)) {
        return [];
    }

    return config.cases
        .map((caseItem) => (caseItem && typeof caseItem === 'object' ? String(caseItem.id || '').trim() : ''))
        .filter((id) => id !== '');
}

/**
 * Rename and prune outgoing edges when named source handles change
 * (intent classifier intents / fork branches).
 *
 * @param {Array} edges
 * @param {string} nodeId
 * @param {string[]} previousIds
 * @param {string[]} nextIds
 * @param {{ allowDefault?: boolean }} [options]
 */
export function syncNamedSourceHandleEdges(edges, nodeId, previousIds, nextIds, options = {}) {
    const allowDefault = options.allowDefault === true;
    const prev = Array.isArray(previousIds) ? previousIds : [];
    const next = Array.isArray(nextIds) ? nextIds : [];
    const nextSet = new Set(next);

    const renameMap = new Map();
    // Index-aligned rename is only safe when the list length is unchanged
    // (in-place id edits). Removals/additions must prune by set membership.
    if (prev.length === next.length) {
        for (let i = 0; i < prev.length; i += 1) {
            if (prev[i] && next[i] && prev[i] !== next[i]) {
                renameMap.set(prev[i], next[i]);
            }
        }
    }

    const result = [];

    for (const edge of edges || []) {
        if (edge.source !== nodeId) {
            result.push(edge);
            continue;
        }

        let handle = edge.sourceHandle || 'default';

        if (renameMap.has(handle)) {
            handle = renameMap.get(handle);
        }

        if (handle === 'default' || handle === '') {
            if (allowDefault) {
                result.push(edge);
            }
            continue;
        }

        if (!nextSet.has(handle)) {
            continue;
        }

        if (handle !== (edge.sourceHandle || 'default')) {
            result.push(
                buildFlowEdge({
                    ...edge,
                    sourceHandle: handle,
                }),
            );
            continue;
        }

        result.push(edge);
    }

    return result;
}

/**
 * Drop orphan named-handle edges for intent_classifier and fork nodes.
 * Also sanitizes intent ids (accents → ASCII) and remaps edges.
 */
export function pruneOrphanNamedHandleEdges(nodes, edges) {
    let nextEdges = edges || [];
    const nextNodes = (nodes || []).map((node) => {
        const nodeType = node.data?.nodeType ?? node.type;
        const config = node.data?.config || {};

        if (nodeType === 'intent_classifier' && Array.isArray(config.intents)) {
            const previousIds = intentIdsFromConfig(config);
            const seen = [];
            const nextIntents = config.intents.map((intent, index) => {
                if (!intent || typeof intent !== 'object') {
                    return intent;
                }
                const raw = typeof intent.id === 'string' && intent.id !== '' ? intent.id : `intent_${index + 1}`;
                const id = uniqueIntentId(raw, seen);
                seen.push(id);
                return { ...intent, id };
            });
            const nextIds = nextIntents
                .map((intent) => (intent && typeof intent === 'object' ? String(intent.id || '') : ''))
                .filter((id) => id !== '');

            nextEdges = syncNamedSourceHandleEdges(nextEdges, node.id, previousIds, nextIds, {
                allowDefault: false,
            });

            return {
                ...node,
                data: {
                    ...node.data,
                    config: {
                        ...config,
                        intents: nextIntents,
                    },
                },
            };
        }

        if (nodeType === 'fork') {
            const ids = forkBranchIdsFromConfig(config);
            nextEdges = syncNamedSourceHandleEdges(nextEdges, node.id, ids, ids, {
                allowDefault: true,
            });
        }

        if (nodeType === 'switch' && Array.isArray(config.cases)) {
            const previousIds = switchCaseIdsFromConfig(config);
            const seen = [];
            const nextCases = config.cases.map((caseItem, index) => {
                if (!caseItem || typeof caseItem !== 'object') {
                    return caseItem;
                }
                const raw =
                    typeof caseItem.id === 'string' && caseItem.id !== ''
                        ? caseItem.id
                        : `case_${index + 1}`;
                const id = uniqueIntentId(raw, seen);
                seen.push(id);
                return { ...caseItem, id };
            });
            const nextIds = nextCases
                .map((caseItem) =>
                    caseItem && typeof caseItem === 'object' ? String(caseItem.id || '') : '',
                )
                .filter((id) => id !== '');

            nextEdges = syncNamedSourceHandleEdges(nextEdges, node.id, previousIds, nextIds, {
                allowDefault: true,
            });

            return {
                ...node,
                data: {
                    ...node.data,
                    config: {
                        ...config,
                        cases: nextCases,
                    },
                },
            };
        }

        return node;
    });

    return { nodes: nextNodes, edges: nextEdges };
}

export function buildFlowEdge(connectionOrEdge) {
    const handle = connectionOrEdge.sourceHandle || 'default';
    const targetHandle = connectionOrEdge.targetHandle || 'default';
    const label = edgeLabelForHandle(handle, targetHandle);

    return {
        id:
            connectionOrEdge.id ||
            `${connectionOrEdge.source}-${connectionOrEdge.target}-${handle}-${targetHandle}-${Date.now()}`,
        source: connectionOrEdge.source,
        target: connectionOrEdge.target,
        sourceHandle: handle,
        targetHandle,
        type: 'workflowEdge',
        animated: false,
        label,
        data: { label },
        style: edgeStyleForHandle(handle, targetHandle),
    };
}

export function normalizeNodeTitle(title) {
    if (typeof title !== 'string') {
        return null;
    }

    const trimmed = title.trim();

    return trimmed === '' ? null : trimmed;
}

export function nodeTitleUniquenessKey(title) {
    const normalized = normalizeNodeTitle(title);

    return normalized === null ? null : normalized.toLowerCase();
}

/**
 * @param {string} base
 * @param {Array<string|null|undefined>} existingTitles
 */
export function uniqueNodeTitle(base, existingTitles = []) {
    const keys = new Set(
        existingTitles.map((title) => nodeTitleUniquenessKey(title)).filter(Boolean),
    );
    const candidate = (base || '').trim();

    if (!keys.has(nodeTitleUniquenessKey(candidate))) {
        return candidate;
    }

    let suffix = 2;

    while (keys.has(nodeTitleUniquenessKey(`${candidate} ${suffix}`))) {
        suffix += 1;
    }

    return `${candidate} ${suffix}`;
}

/**
 * @param {Array<{ data?: { title?: string, nodeType?: string }, type?: string }>} nodes
 */
export function collectNodeTitles(nodes = []) {
    return nodes
        .filter((node) => node.data?.nodeType !== 'note' && node.type !== 'stickyNote')
        .map((node) => node.data?.title)
        .filter((title) => typeof title === 'string' && title.trim() !== '');
}

export function toFlowNodes(packageNodes, nodeTypesMeta, annotations = []) {
    const workflowNodes = (packageNodes || []).map((node) => {
        const meta = nodeTypesMeta[node.type] || {};
        const title = normalizeNodeTitle(typeof node.title === 'string' ? node.title : null);

        return {
            id: node.id,
            type: 'workflowNode',
            position: {
                x: node.position?.x ?? 0,
                y: node.position?.y ?? 0,
            },
            data: {
                nodeType: node.type,
                label: meta.label || node.type,
                ...(title ? { title } : {}),
                category: meta.category || 'flow',
                icon: meta.icon || 'circle',
                config: normalizeNodeData(node.data),
                executionStatus: null,
            },
            selected: false,
        };
    });

    const noteNodes = (annotations || []).map((note) => ({
        id: note.id,
        type: 'stickyNote',
        position: {
            x: note.position?.x ?? 0,
            y: note.position?.y ?? 0,
        },
        data: {
            nodeType: 'note',
            label: 'Note',
            category: 'utilities',
            icon: 'sticky',
            config: normalizeNodeData(note.data ?? { text: note.text ?? '' }),
            executionStatus: null,
        },
        selected: false,
    }));

    return [...workflowNodes, ...noteNodes];
}

export function toFlowEdges(packageEdges) {
    return (packageEdges || []).map((edge) => buildFlowEdge(edge));
}

export function toPackageGraph(nodes, edges, viewport) {
    const workflowNodes = [];
    const annotations = [];

    for (const node of nodes) {
        if (node.type === 'stickyNote' || node.data?.nodeType === 'note') {
            annotations.push({
                id: node.id,
                type: 'note',
                position: { x: node.position.x, y: node.position.y },
                data: node.data.config || {},
            });
            continue;
        }

        workflowNodes.push({
            id: node.id,
            type: node.data.nodeType,
            position: { x: node.position.x, y: node.position.y },
            ...(normalizeNodeTitle(node.data.title) ? { title: normalizeNodeTitle(node.data.title) } : {}),
            data: node.data.config || {},
        });
    }

    return {
        version: 1,
        nodes: workflowNodes,
        edges: edges
            .filter((edge) => {
                const source = nodes.find((node) => node.id === edge.source);
                const target = nodes.find((node) => node.id === edge.target);
                return (
                    source?.data?.nodeType !== 'note' &&
                    target?.data?.nodeType !== 'note' &&
                    source?.type !== 'stickyNote' &&
                    target?.type !== 'stickyNote'
                );
            })
            .map((edge) => ({
                id: edge.id,
                source: edge.source,
                target: edge.target,
                sourceHandle: edge.sourceHandle || 'default',
                targetHandle: edge.targetHandle || 'default',
            })),
        annotations,
        viewport: viewport || { x: 0, y: 0, zoom: 1 },
    };
}

export const FLOW_NODE_WIDTH = 244;
export const FLOW_NODE_HEIGHT = 96;

export function dropFlowPosition(screenToFlowPosition, clientX, clientY) {
    const position = screenToFlowPosition({ x: clientX, y: clientY });

    return {
        x: position.x - FLOW_NODE_WIDTH / 2,
        y: position.y - FLOW_NODE_HEIGHT / 2,
    };
}

export function createNodeId(type) {
    return `${type}_${Date.now()}`;
}

export function buildFlowNode(type, position, nodeTypesMeta, config = {}, title = null) {
    const meta = nodeTypesMeta[type] || {};
    const normalizedTitle = normalizeNodeTitle(title);

    if (type === 'note') {
        return {
            id: createNodeId('note'),
            type: 'stickyNote',
            position,
            data: {
                nodeType: 'note',
                label: meta.label || 'Note',
                category: 'utilities',
                icon: 'sticky',
                config: { text: '', ...config },
                executionStatus: null,
            },
        };
    }

    return {
        id: createNodeId(type),
        type: 'workflowNode',
        position,
        data: {
            nodeType: type,
            label: meta.label || type,
            ...(normalizedTitle ? { title: normalizedTitle } : {}),
            category: meta.category || 'flow',
            icon: meta.icon || 'circle',
            config,
            executionStatus: null,
        },
    };
}

function nodeHandlePosition(node, role) {
    const x = node.position.x;
    const y = node.position.y;

    if (role === 'source') {
        return { x: x + FLOW_NODE_WIDTH, y: y + FLOW_NODE_HEIGHT / 2 };
    }

    return { x, y: y + FLOW_NODE_HEIGHT / 2 };
}

function pointToSegmentDistance(px, py, x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const lengthSquared = dx * dx + dy * dy;

    if (lengthSquared === 0) {
        return Math.hypot(px - x1, py - y1);
    }

    let t = ((px - x1) * dx + (py - y1) * dy) / lengthSquared;
    t = Math.max(0, Math.min(1, t));

    const projX = x1 + t * dx;
    const projY = y1 + t * dy;

    return Math.hypot(px - projX, py - projY);
}

const EDGE_HIT_THRESHOLD = 80;

export function findEdgeNearPoint(nodes, edges, point, threshold = EDGE_HIT_THRESHOLD) {
    const nodeMap = new Map(nodes.map((node) => [node.id, node]));
    let closest = null;
    let closestDistance = threshold;

    for (const edge of edges) {
        const source = nodeMap.get(edge.source);
        const target = nodeMap.get(edge.target);

        if (!source || !target) {
            continue;
        }

        const start = nodeHandlePosition(source, 'source');
        const end = nodeHandlePosition(target, 'target');
        const distance = pointToSegmentDistance(point.x, point.y, start.x, start.y, end.x, end.y);

        if (distance < closestDistance) {
            closestDistance = distance;
            closest = edge;
        }
    }

    return closest;
}

export function edgeMidpoint(nodes, edge) {
    const nodeMap = new Map(nodes.map((node) => [node.id, node]));
    const source = nodeMap.get(edge.source);
    const target = nodeMap.get(edge.target);

    if (!source || !target) {
        return { x: 0, y: 0 };
    }

    const start = nodeHandlePosition(source, 'source');
    const end = nodeHandlePosition(target, 'target');

    return {
        x: (start.x + end.x) / 2,
        y: (start.y + end.y) / 2,
    };
}

export function spliceNodeIntoEdge(newNodeId, edge, edges) {
    const remaining = edges.filter((item) => item.id !== edge.id);

    const incoming = buildFlowEdge({
        source: edge.source,
        target: newNodeId,
        sourceHandle: edge.sourceHandle || 'default',
        targetHandle: 'default',
    });

    const outgoing = buildFlowEdge({
        source: newNodeId,
        target: edge.target,
        sourceHandle: 'default',
        targetHandle: edge.targetHandle || 'default',
    });

    return [...remaining, incoming, outgoing];
}

export function canSpliceNodeType(type) {
    return type !== 'start' && type !== 'stop' && type !== 'note';
}
