/** @param {Record<string, unknown>} data */
export function resolveAgentConfigMode(data = {}) {
    if (data.config_mode === 'inline' || data.config_mode === 'existing') {
        return data.config_mode;
    }

    return data.agent_id != null && data.agent_id !== '' ? 'existing' : 'inline';
}

/** @param {Record<string, unknown>} data */
export function isToolModeEnabled(data = {}) {
    return data.tool_mode === true || data.tool_mode === 1 || data.tool_mode === '1';
}

/** @param {Record<string, unknown>|undefined} meta */
export function isNodeTypeToolable(meta) {
    return meta?.toolable === true;
}

/**
 * @param {Record<string, unknown>} data
 * @param {Record<string, unknown>} meta
 */
export function defaultToolExposure(data = {}, meta = {}) {
    const exposure = data.tool_exposure && typeof data.tool_exposure === 'object' ? data.tool_exposure : {};
    const slugPrefix = meta?.tool_exposure?.slug_prefix || 'call_agent';
    const metaDescription =
        meta?.tool_exposure?.default_description || 'Delegate a task to this specialized agent.';
    const instructions =
        typeof data.instructions === 'string' && data.instructions.trim() !== ''
            ? data.instructions.trim()
            : '';

    const rawParameters =
        exposure.parameters && typeof exposure.parameters === 'object' ? exposure.parameters : {};
    const rawInput =
        rawParameters.input && typeof rawParameters.input === 'object' ? rawParameters.input : {};

    const parameters = {
        ...rawParameters,
        input: {
            description: 'Task for the specialist',
            ...rawInput,
            controlled_by: 'caller',
        },
    };

    return {
        slug: typeof exposure.slug === 'string' && exposure.slug.trim() !== '' ? exposure.slug.trim() : slugPrefix,
        description:
            typeof exposure.description === 'string' && exposure.description.trim() !== ''
                ? exposure.description.trim()
                : instructions || metaDescription,
        parameters,
    };
}

/** @param {string} slug */
export function isValidToolExposureSlug(slug) {
    return typeof slug === 'string' && /^[A-Za-z_][A-Za-z0-9_]*$/.test(slug.trim());
}

/**
 * @param {Record<string, unknown>} data
 * @param {Record<string, unknown>} meta
 */
export function resolveToolExposureForSave(data = {}, meta = {}) {
    const defaults = defaultToolExposure(data, meta);
    const exposure = data.tool_exposure && typeof data.tool_exposure === 'object' ? data.tool_exposure : {};
    const slugRaw = typeof exposure.slug === 'string' ? exposure.slug.trim() : '';
    const descriptionRaw = typeof exposure.description === 'string' ? exposure.description.trim() : '';

    return {
        slug: slugRaw !== '' ? slugRaw : defaults.slug,
        description: descriptionRaw !== '' ? descriptionRaw : defaults.description,
        parameters: defaults.parameters,
    };
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

    if (node.type === 'switch') {
        if (!Array.isArray(data.cases) || data.cases.length === 0) {
            data.cases = [
                {
                    id: 'case_1',
                    label: 'Case 1',
                    state_key: 'input',
                    operator: 'not_empty',
                    value: null,
                    value_type: 'auto',
                    strict: false,
                },
            ];
        }
    }

    if (node.type === 'llm' && !data.output_key) {
        data.output_key = 'llm_response';
    }

    if (node.type === 'intent_classifier') {
        if (!data.output_key) {
            data.output_key = 'intent';
        }
        if (!Array.isArray(data.intents) || data.intents.length === 0) {
            data.intents = [
                {
                    id: 'after_sales',
                    name: 'After sales',
                    description: 'Question related to after sales',
                },
                {
                    id: 'how_to',
                    name: 'How to use',
                    description: 'Questions about how to use products',
                },
                {
                    id: 'other',
                    name: 'Other',
                    description: 'Other questions',
                },
            ];
        }
        if (data.message === undefined) {
            data.message = '{{input}}';
        }
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
