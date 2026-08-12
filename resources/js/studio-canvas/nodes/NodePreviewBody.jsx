import { isToolModeEnabled, resolveAgentConfigMode } from '../inspector/nodeUtils';

function forkBranches(config) {
    if (!config || !Array.isArray(config.branches)) {
        return [];
    }

    return config.branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

function intentList(config) {
    if (!config || !Array.isArray(config.intents)) {
        return [];
    }

    return config.intents.filter((intent) => intent && typeof intent === 'object' && intent.id);
}

function switchCaseList(config) {
    if (!config || !Array.isArray(config.cases)) {
        return [];
    }

    return config.cases.filter((caseItem) => caseItem && typeof caseItem === 'object' && caseItem.id);
}

function PreviewBadge({ children }) {
    if (!children) {
        return null;
    }

    return <div className="ab-flow-node-badge">{children}</div>;
}

function PreviewRow({ anchor, label, className = '', muted = false }) {
    return (
        <div
            className={`ab-flow-node-preview-row${muted ? ' is-muted' : ''}${className ? ` ${className}` : ''}`}
            data-ab-handle-anchor={anchor}
        >
            <span className="ab-flow-node-preview-row-label">{label}</span>
        </div>
    );
}

function PreviewMeta({ children, className = '' }) {
    if (!children) {
        return null;
    }

    return <div className={`ab-flow-node-meta${className ? ` ${className}` : ''}`}>{children}</div>;
}

export function getForkBranches(config) {
    return forkBranches(config);
}

export function getIntentIds(config) {
    return intentList(config).map((intent) => intent.id);
}

export function getSwitchCaseIds(config) {
    return switchCaseList(config).map((caseItem) => caseItem.id);
}

export default function NodePreviewBody({
    nodeType,
    config = {},
    agents = [],
    workflows = [],
    tools = [],
    knowledgeBases = [],
    mcpServers = [],
    loopIteration = null,
}) {
    if (nodeType === 'start' || nodeType === 'stop') {
        return null;
    }

    if (nodeType === 'llm') {
        const model = config.model || config.provider;
        return model ? <PreviewBadge>{model}</PreviewBadge> : null;
    }

    if (nodeType === 'agent') {
        const mode = resolveAgentConfigMode(config);
        const toolMode = isToolModeEnabled(config);
        const agentName =
            mode === 'existing' && config.agent_id
                ? agents.find((agent) => String(agent.id) === String(config.agent_id))?.name
                : null;
        const inlineMeta =
            mode === 'inline' ? [config.provider, config.model].filter(Boolean).join(' / ') || null : null;

        return (
            <div className="ab-flow-node-preview">
                {(agentName || inlineMeta) && <PreviewBadge>{agentName || inlineMeta}</PreviewBadge>}
                {toolMode ? (
                    <>
                        <PreviewRow
                            anchor="tools"
                            label="tools"
                            className="ab-flow-handle-label-tools"
                        />
                        <PreviewRow
                            anchor="toolset"
                            label="toolset"
                            className="ab-flow-handle-label-toolset"
                        />
                    </>
                ) : (
                    <>
                        <PreviewRow anchor="input" label="input" />
                        <PreviewRow
                            anchor="tools"
                            label="tools"
                            className="ab-flow-handle-label-tools"
                        />
                        <PreviewRow anchor="response" label="response" />
                    </>
                )}
            </div>
        );
    }

    if (nodeType === 'run_workflow') {
        const toolMode = isToolModeEnabled(config);
        const workflowName = config.workflow_id
            ? workflows.find((workflow) => String(workflow.id) === String(config.workflow_id))?.name
            : null;

        return (
            <div className="ab-flow-node-preview">
                {workflowName && <PreviewBadge>{workflowName}</PreviewBadge>}
                {toolMode && (
                    <PreviewRow
                        anchor="toolset"
                        label="toolset"
                        className="ab-flow-handle-label-toolset"
                    />
                )}
            </div>
        );
    }

    if (nodeType === 'condition') {
        return (
            <div className="ab-flow-node-preview">
                <PreviewRow
                    anchor="true"
                    label="true"
                    className="ab-flow-handle-label-true"
                />
                <PreviewRow
                    anchor="false"
                    label="false"
                    className="ab-flow-handle-label-false"
                />
            </div>
        );
    }

    if (nodeType === 'switch') {
        const cases = switchCaseList(config);

        return (
            <div className="ab-flow-node-preview">
                <PreviewRow anchor="default" label="default" muted />
                {cases.length === 0 && <PreviewMeta>No cases</PreviewMeta>}
                {cases.map((caseItem) => (
                    <PreviewRow
                        key={caseItem.id}
                        anchor={`case:${caseItem.id}`}
                        label={caseItem.label || caseItem.id}
                    />
                ))}
            </div>
        );
    }

    if (nodeType === 'loop') {
        return (
            <div className="ab-flow-node-preview">
                {loopIteration && (
                    <PreviewMeta>
                        {loopIteration.iteration} / {loopIteration.maxSteps}
                    </PreviewMeta>
                )}
                <PreviewRow
                    anchor="continue"
                    label="continue"
                    className="ab-flow-handle-label-continue"
                />
                <PreviewRow
                    anchor="exit"
                    label="exit"
                    className="ab-flow-handle-label-exit"
                />
            </div>
        );
    }

    if (nodeType === 'fork') {
        const branches = forkBranches(config);

        return (
            <div className="ab-flow-node-preview">
                <PreviewRow anchor="default" label="default" muted />
                {branches.map((branchId) => (
                    <PreviewRow key={branchId} anchor={`branch:${branchId}`} label={branchId} />
                ))}
            </div>
        );
    }

    if (nodeType === 'intent_classifier') {
        const intents = intentList(config);

        return (
            <div className="ab-flow-node-preview">
                {config.model && <PreviewBadge>{config.model}</PreviewBadge>}
                {intents.length === 0 && <PreviewMeta>No intents</PreviewMeta>}
                {intents.map((intent) => (
                    <PreviewRow
                        key={intent.id}
                        anchor={`intent:${intent.id}`}
                        label={intent.name || intent.id}
                    />
                ))}
            </div>
        );
    }

    if (nodeType === 'set_state') {
        const assignments = Array.isArray(config.assignments) ? config.assignments : [];
        const keys = assignments
            .map((item) => item?.key || item?.name)
            .filter(Boolean)
            .slice(0, 4);

        return keys.length > 0 ? <PreviewMeta>{keys.join(', ')}</PreviewMeta> : null;
    }

    if (nodeType === 'rag') {
        const kb = config.knowledge_base_id
            ? knowledgeBases.find((item) => String(item.id) === String(config.knowledge_base_id))
            : null;
        const label = kb?.name || config.model || null;
        return label ? <PreviewBadge>{label}</PreviewBadge> : null;
    }

    if (nodeType === 'tool') {
        const tool = tools.find((item) => item.ref === config.tool_ref);
        return config.tool_ref ? <PreviewBadge>{tool?.label || config.tool_ref}</PreviewBadge> : null;
    }

    if (nodeType === 'mcp') {
        const server = mcpServers.find((item) => String(item.id) === String(config.mcp_server_id));
        return server?.name || config.tool_name ? (
            <PreviewBadge>{server?.name || config.tool_name}</PreviewBadge>
        ) : null;
    }

    if (nodeType === 'delay') {
        const seconds = config.seconds ?? config.delay_seconds;
        return seconds != null ? <PreviewMeta>{seconds}s</PreviewMeta> : null;
    }

    if (nodeType === 'human') {
        return config.prompt ? (
            <PreviewMeta className="ab-flow-node-meta--clamp">{String(config.prompt).slice(0, 80)}</PreviewMeta>
        ) : null;
    }

    if (nodeType === 'invoke') {
        return config.class || config.method ? (
            <PreviewMeta>{[config.class, config.method].filter(Boolean).join('::')}</PreviewMeta>
        ) : null;
    }

    return null;
}
