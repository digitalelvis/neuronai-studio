window.workflowInspector = function (agents, tools, mcpServers) {
    return {
        selectedNode: null,
        agents: agents || [],
        tools: tools || [],
        mcpServers: mcpServers || [],

        init() {
            window.addEventListener('canvas-node-selected', (e) => {
                this.selectedNode = e.detail ? { ...e.detail, data: { ...(e.detail.data || {}) } } : null;

                if (this.selectedNode?.type === 'agent' && this.selectedNode.data.agent_id != null && this.selectedNode.data.agent_id !== '') {
                    this.selectedNode.data.agent_id = String(this.selectedNode.data.agent_id);
                }

                if (this.selectedNode?.type === 'tool' || this.selectedNode?.type === 'mcp') {
                    if (!this.selectedNode.data.output_key) {
                        this.selectedNode.data.output_key = this.selectedNode.type === 'mcp' ? 'mcp_result' : 'tool_result';
                    }

                    if (this.selectedNode.data.parameters && !this.selectedNode.data.parameters_json) {
                        this.selectedNode.data.parameters_json = JSON.stringify(this.selectedNode.data.parameters, null, 2);
                    }
                }
            });

            window.addEventListener('canvas-inspector-flush', () => {
                this.flushBeforeSave();
            });
        },

        syncNode() {
            if (!this.selectedNode) return;

            this.$nextTick(() => {
                window.dispatchEvent(
                    new CustomEvent('canvas-node-updated', {
                        detail: {
                            id: this.selectedNode.id,
                            data: { ...this.selectedNode.data },
                        },
                    }),
                );
            });
        },

        flushBeforeSave() {
            if (!this.selectedNode) return;

            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: {
                        id: this.selectedNode.id,
                        data: { ...this.selectedNode.data },
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
            if (!this.selectedNode || ['start', 'stop'].includes(this.selectedNode.type)) {
                return;
            }

            window.dispatchEvent(new CustomEvent('canvas-remove-node'));
            this.selectedNode = null;
        },
    };
};
