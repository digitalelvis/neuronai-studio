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
