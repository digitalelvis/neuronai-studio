window.workflowInspector = function (agents) {
    return {
        selectedNode: null,
        agents: agents || [],

        init() {
            window.addEventListener('canvas-node-selected', (e) => {
                this.selectedNode = e.detail ? { ...e.detail, data: { ...(e.detail.data || {}) } } : null;
            });
        },

        syncNode() {
            if (!this.selectedNode) return;
            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: {
                        id: this.selectedNode.id,
                        data: this.selectedNode.data,
                    },
                }),
            );
        },

        removeSelected() {
            window.dispatchEvent(new CustomEvent('canvas-remove-node'));
            this.selectedNode = null;
        },
    };
};
