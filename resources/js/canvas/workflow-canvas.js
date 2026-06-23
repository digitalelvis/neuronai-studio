window.workflowCanvas = function (initialGraph, nodeTypes, agents) {
    return {
        graph: initialGraph || { version: 1, nodes: [], edges: [], viewport: { x: 0, y: 0, zoom: 1 } },
        nodeTypes: nodeTypes || {},
        agents: agents || [],
        selectedNode: null,
        dragNode: null,
        dragOffset: { x: 0, y: 0 },

        init() {
            this.renderNodes();
            this.renderEdges();
            window.addEventListener('mousemove', (e) => this.onMouseMove(e));
            window.addEventListener('mouseup', () => this.onMouseUp());
        },

        renderNodes() {
            const container = this.$refs.nodes;
            if (!container) return;
            container.innerHTML = '';

            (this.graph.nodes || []).forEach((node) => {
                const el = document.createElement('div');
                el.className = 'ab-node' + (this.selectedNode?.id === node.id ? ' selected' : '');
                el.style.left = (node.position?.x || 0) + 'px';
                el.style.top = (node.position?.y || 0) + 'px';
                el.dataset.id = node.id;
                el.innerHTML = `<div class="ab-node-type">${node.type}</div><div class="ab-node-label">${nodeTypes[node.type]?.label || node.type}</div>`;
                el.addEventListener('mousedown', (e) => this.startDrag(e, node));
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectNode(node);
                });
                container.appendChild(el);
            });
        },

        renderEdges() {
            const svg = this.$refs.edges;
            if (!svg) return;
            svg.innerHTML = '';

            (this.graph.edges || []).forEach((edge) => {
                const source = this.graph.nodes.find((n) => n.id === edge.source);
                const target = this.graph.nodes.find((n) => n.id === edge.target);
                if (!source || !target) return;

                const x1 = (source.position?.x || 0) + 70;
                const y1 = (source.position?.y || 0) + 30;
                const x2 = (target.position?.x || 0) + 70;
                const y2 = (target.position?.y || 0) + 30;

                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', x1);
                line.setAttribute('y1', y1);
                line.setAttribute('x2', x2);
                line.setAttribute('y2', y2);
                line.setAttribute('stroke', '#6366f1');
                line.setAttribute('stroke-width', '2');
                svg.appendChild(line);
            });
        },

        addNode(type) {
            const id = type + '_' + Date.now();
            const node = {
                id,
                type,
                position: { x: 150 + Math.random() * 200, y: 100 + Math.random() * 200 },
                data: {},
            };
            this.graph.nodes.push(node);
            this.selectNode(node);
            this.renderNodes();
            this.renderEdges();
        },

        selectNode(node) {
            if (!node.data) node.data = {};
            this.selectedNode = node;
            this.renderNodes();
        },

        removeSelected() {
            if (!this.selectedNode) return;
            const id = this.selectedNode.id;
            this.graph.nodes = this.graph.nodes.filter((n) => n.id !== id);
            this.graph.edges = this.graph.edges.filter((e) => e.source !== id && e.target !== id);
            this.selectedNode = null;
            this.renderNodes();
            this.renderEdges();
        },

        startDrag(e, node) {
            this.dragNode = node;
            this.dragOffset = {
                x: e.clientX - (node.position?.x || 0),
                y: e.clientY - (node.position?.y || 0),
            };
        },

        onMouseMove(e) {
            if (!this.dragNode) return;
            this.dragNode.position = {
                x: e.clientX - this.dragOffset.x,
                y: e.clientY - this.dragOffset.y,
            };
            this.renderNodes();
            this.renderEdges();
        },

        onMouseUp() {
            this.dragNode = null;
        },

        exportGraph() {
            return this.graph;
        },

        syncAndSave() {
            @this.call('saveGraph', this.exportGraph());
        },
    };
};
