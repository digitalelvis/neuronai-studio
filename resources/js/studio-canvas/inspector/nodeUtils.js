/** @param {Record<string, unknown>} data */
export function resolveAgentConfigMode(data = {}) {
    if (data.config_mode === 'inline' || data.config_mode === 'existing') {
        return data.config_mode;
    }

    return data.agent_id != null && data.agent_id !== '' ? 'existing' : 'inline';
}

export function normalizeNodeForEdit(node) {
    if (!node) {
        return null;
    }

    const data = { ...(node.data || {}) };

    if (node.type === 'agent') {
        if (data.agent_id != null && data.agent_id !== '') {
            data.agent_id = String(data.agent_id);
        }

        if (!data.config_mode) {
            data.config_mode = resolveAgentConfigMode(data);
        }

        if (!data.output_key) {
            data.output_key = 'agent_response';
        }
    }

    if (node.type === 'tool' || node.type === 'mcp') {
        if (!data.output_key) {
            data.output_key = node.type === 'mcp' ? 'mcp_result' : 'tool_result';
        }
        if (data.parameters && !data.parameters_json) {
            data.parameters_json = JSON.stringify(data.parameters, null, 2);
        }
    }

    if (node.type === 'human' && !data.output_key) {
        data.output_key = 'human_response';
    }

    if (node.type === 'condition' && !data.operator) {
        data.operator = 'not_empty';
    }

    if (node.type === 'llm' && !data.output_key) {
        data.output_key = 'llm_response';
    }

    if (node.type === 'invoke') {
        if (!data.output_key) {
            data.output_key = 'invoke_result';
        }
    }

    return { ...node, data };
}

export function buildEditPayloadFromFlowNode(node) {
    if (!node?.id) {
        return null;
    }

    return {
        id: node.id,
        type: node.data?.nodeType ?? node.type,
        position: node.position,
        data: node.data?.config || {},
    };
}

export function dispatchNodeEdit(node) {
    const payload = buildEditPayloadFromFlowNode(node);

    if (!payload) {
        return;
    }

    window.dispatchEvent(new CustomEvent('canvas-node-edit', { detail: payload }));
}
