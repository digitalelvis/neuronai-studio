window.workflowInspector = function (agents, tools) {
    return {
        selectedNode: null,
        agents: agents || [],
        tools: tools || [],

        init() {
            window.addEventListener('canvas-node-selected', (e) => {
                this.selectedNode = e.detail ? { ...e.detail, data: { ...(e.detail.data || {}) } } : null;

                if (this.selectedNode?.type === 'tool') {
                    if (!this.selectedNode.data.output_key) {
                        this.selectedNode.data.output_key = 'tool_result';
                    }

                    if (this.selectedNode.data.parameters && !this.selectedNode.data.parameters_json) {
                        this.selectedNode.data.parameters_json = JSON.stringify(this.selectedNode.data.parameters, null, 2);
                    }
                }
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

        syncParameters() {
            if (!this.selectedNode) return;

            try {
                this.selectedNode.data.parameters = JSON.parse(this.selectedNode.data.parameters_json || '{}');
            } catch (error) {
                return;
            }

            this.syncNode();
        },

        removeSelected() {
            window.dispatchEvent(new CustomEvent('canvas-remove-node'));
            this.selectedNode = null;
        },
    };
};
