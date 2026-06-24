const CATEGORY_COLORS = {
    flow: '#6366f1',
    ai: '#8b5cf6',
    logic: '#f59e0b',
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

export function edgeLabelForHandle(handle) {
    if (handle === 'true') {
        return 'true';
    }

    if (handle === 'false') {
        return 'false';
    }

    return undefined;
}

export function edgeStyleForHandle(handle) {
    if (handle === 'true') {
        return { stroke: '#22c55e', strokeWidth: 2 };
    }

    if (handle === 'false') {
        return { stroke: '#ef4444', strokeWidth: 2 };
    }

    return { stroke: '#6366f1', strokeWidth: 2 };
}

export function buildFlowEdge(connectionOrEdge) {
    const handle = connectionOrEdge.sourceHandle || 'default';
    const label = edgeLabelForHandle(handle);

    return {
        id:
            connectionOrEdge.id ||
            `${connectionOrEdge.source}-${connectionOrEdge.target}-${handle}-${Date.now()}`,
        source: connectionOrEdge.source,
        target: connectionOrEdge.target,
        sourceHandle: handle,
        targetHandle: connectionOrEdge.targetHandle || 'default',
        type: 'workflowEdge',
        animated: false,
        label,
        data: { label },
        style: edgeStyleForHandle(handle),
    };
}

export function toFlowNodes(packageNodes, nodeTypesMeta) {
    return (packageNodes || []).map((node) => {
        const meta = nodeTypesMeta[node.type] || {};

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
                category: meta.category || 'flow',
                icon: meta.icon || 'circle',
                config: normalizeNodeData(node.data),
                executionStatus: null,
            },
            selected: false,
        };
    });
}

export function toFlowEdges(packageEdges) {
    return (packageEdges || []).map((edge) => buildFlowEdge(edge));
}

export function toPackageGraph(nodes, edges, viewport) {
    return {
        version: 1,
        nodes: nodes.map((node) => ({
            id: node.id,
            type: node.data.nodeType,
            position: { x: node.position.x, y: node.position.y },
            data: node.data.config || {},
        })),
        edges: edges.map((edge) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            sourceHandle: edge.sourceHandle || 'default',
            targetHandle: edge.targetHandle || 'default',
        })),
        viewport: viewport || { x: 0, y: 0, zoom: 1 },
    };
}

export const FLOW_NODE_WIDTH = 180;
export const FLOW_NODE_HEIGHT = 80;

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

export function buildFlowNode(type, position, nodeTypesMeta, config = {}) {
    const meta = nodeTypesMeta[type] || {};

    return {
        id: createNodeId(type),
        type: 'workflowNode',
        position,
        data: {
            nodeType: type,
            label: meta.label || type,
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
    return type !== 'start' && type !== 'stop';
}
