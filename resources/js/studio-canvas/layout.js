import dagre from '@dagrejs/dagre';

import { FLOW_NODE_HEIGHT, FLOW_NODE_WIDTH } from './graph';

const NODE_WIDTH = FLOW_NODE_WIDTH;
const NODE_HEIGHT = FLOW_NODE_HEIGHT;

export function layoutWithDagre(nodes, edges, direction = 'LR') {
    const graph = new dagre.graphlib.Graph();
    graph.setDefaultEdgeLabel(() => ({}));
    graph.setGraph({
        rankdir: direction,
        nodesep: 80,
        ranksep: 120,
        marginx: 40,
        marginy: 40,
    });

    nodes.forEach((node) => {
        graph.setNode(node.id, { width: NODE_WIDTH, height: NODE_HEIGHT });
    });

    edges.forEach((edge) => {
        graph.setEdge(edge.source, edge.target);
    });

    dagre.layout(graph);

    return nodes.map((node) => {
        const position = graph.node(node.id);

        return {
            ...node,
            position: {
                x: position.x - NODE_WIDTH / 2,
                y: position.y - NODE_HEIGHT / 2,
            },
        };
    });
}

function nodeBounds(node, width = NODE_WIDTH, height = NODE_HEIGHT) {
    const x = node.position?.x ?? 0;
    const y = node.position?.y ?? 0;

    return {
        left: x,
        top: y,
        right: x + width,
        bottom: y + height,
    };
}

function boundsOverlap(a, b) {
    return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
}

export function nodesHaveOverlap(nodes, width = NODE_WIDTH, height = NODE_HEIGHT) {
    const workflowNodes = (nodes || []).filter(
        (node) => node.data?.nodeType !== 'note' && node.type !== 'stickyNote',
    );

    for (let i = 0; i < workflowNodes.length; i += 1) {
        const a = nodeBounds(workflowNodes[i], width, height);

        for (let j = i + 1; j < workflowNodes.length; j += 1) {
            const b = nodeBounds(workflowNodes[j], width, height);

            if (boundsOverlap(a, b)) {
                return true;
            }
        }
    }

    return false;
}

export function ensureLayoutedGraph(nodes, edges) {
    if (!nodesHaveOverlap(nodes)) {
        return nodes;
    }

    const workflowOnly = nodes.filter(
        (node) => node.data?.nodeType !== 'note' && node.type !== 'stickyNote',
    );
    const notes = nodes.filter(
        (node) => node.data?.nodeType === 'note' || node.type === 'stickyNote',
    );

    return [...layoutWithDagre(workflowOnly, edges), ...notes];
}
