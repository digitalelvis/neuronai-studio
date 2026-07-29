import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { Handle, NodeToolbar, Position, useUpdateNodeInternals } from '@xyflow/react';
import {
    ChevronDown,
    ChevronUp,
    Copy,
    Settings2,
    SlidersHorizontal,
    Trash2,
} from 'lucide-react';
import { useCanvasUi } from '../CanvasUiContext';
import { categoryColor } from '../graph';
import { normalizeNodeForEdit, resolveAgentConfigMode, isToolModeEnabled } from '../inspector/nodeUtils';
import NodeConfigForm from '../inspector/NodeConfigForm';
import { NodeTypeIcon } from './nodeIcons';

const DENSE_TYPES = new Set(['agent', 'llm', 'rag', 'mcp', 'tool']);

const AGENT_HANDLE_FALLBACKS = {
    tools: 'calc(100% - 18.5rem)',
    input: 'calc(100% - 14.75rem)',
    response: 'calc(100% - 5.25rem)',
    toolset: 'calc(100% - 8.5rem)',
};

function forkBranches(config) {
    if (!config || !Array.isArray(config.branches)) {
        return [];
    }

    return config.branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

function measureHandleAnchor(nodeEl, name) {
    const anchor = nodeEl.querySelector(`[data-ab-handle-anchor="${name}"]`);
    if (!anchor) {
        return null;
    }

    const nodeRect = nodeEl.getBoundingClientRect();
    if (nodeRect.height <= 0) {
        return null;
    }

    const anchorRect = anchor.getBoundingClientRect();
    const centerY = anchorRect.top + anchorRect.height / 2 - nodeRect.top;
    const percent = (centerY / nodeRect.height) * 100;
    return `${Math.round(percent * 100) / 100}%`;
}

function useAgentHandleTops(nodeId, nodeRef, enabled, revision) {
    const updateNodeInternals = useUpdateNodeInternals();
    const [tops, setTops] = useState(null);
    const topsRef = useRef(null);

    useLayoutEffect(() => {
        if (!enabled) {
            topsRef.current = null;
            setTops(null);
            return undefined;
        }

        const nodeEl = nodeRef.current;
        if (!nodeEl) {
            return undefined;
        }

        const sync = () => {
            const next = {
                tools: measureHandleAnchor(nodeEl, 'tools'),
                input: measureHandleAnchor(nodeEl, 'input'),
                response: measureHandleAnchor(nodeEl, 'response'),
                toolset: measureHandleAnchor(nodeEl, 'toolset'),
            };
            const prev = topsRef.current;
            const changed =
                !prev ||
                prev.tools !== next.tools ||
                prev.input !== next.input ||
                prev.response !== next.response ||
                prev.toolset !== next.toolset;

            if (!changed) {
                return;
            }

            topsRef.current = next;
            setTops(next);
            updateNodeInternals(nodeId);
        };

        sync();
        const observer = new ResizeObserver(sync);
        observer.observe(nodeEl);
        return () => observer.disconnect();
    }, [enabled, nodeId, nodeRef, revision, updateNodeInternals]);

    return tops;
}

function NodeHandles({ nodeType, config, expanded = false, handleTops = null }) {
    if (nodeType === 'start') {
        return <Handle type="source" position={Position.Right} id="default" className="ab-flow-handle" />;
    }

    if (nodeType === 'stop') {
        return <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />;
    }

    if (nodeType === 'condition') {
        return (
            <>
                <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="true"
                    className="ab-flow-handle ab-flow-handle-true"
                    style={{ top: '35%' }}
                />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="false"
                    className="ab-flow-handle ab-flow-handle-false"
                    style={{ top: '65%' }}
                />
            </>
        );
    }

    if (nodeType === 'fork') {
        const branches = forkBranches(config);
        const count = branches.length;

        return (
            <>
                <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="default"
                    className="ab-flow-handle"
                    style={{ top: '18%' }}
                />
                {branches.map((branchId, index) => (
                    <Handle
                        key={branchId}
                        type="source"
                        position={Position.Right}
                        id={branchId}
                        className="ab-flow-handle"
                        style={{ top: `${28 + ((index + 1) / (count + 1)) * 55}%` }}
                    />
                ))}
            </>
        );
    }

    if (nodeType === 'loop') {
        return (
            <>
                <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="continue"
                    className="ab-flow-handle ab-flow-handle-continue"
                    style={{ top: '35%' }}
                />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="exit"
                    className="ab-flow-handle ab-flow-handle-exit"
                    style={{ top: '65%' }}
                />
            </>
        );
    }

    if (nodeType === 'agent') {
        const toolMode = isToolModeEnabled(config || {});
        // D8: tools target visible for inline and existing supervisors.
        // v1: specialists in Tool Mode keep tools target so they can bind tool/mcp too.
        const showToolsTarget = true;
        const topFor = (name) => handleTops?.[name] || AGENT_HANDLE_FALLBACKS[name];

        if (toolMode) {
            if (!expanded) {
                return (
                    <>
                        {showToolsTarget && (
                            <Handle
                                type="target"
                                position={Position.Left}
                                id="tools"
                                className="ab-flow-handle ab-flow-handle-tools"
                                style={{ top: '38%' }}
                            />
                        )}
                        <Handle
                            type="source"
                            position={Position.Right}
                            id="toolset"
                            className="ab-flow-handle ab-flow-handle-toolset"
                        />
                    </>
                );
            }

            return (
                <>
                    {showToolsTarget && (
                        <Handle
                            type="target"
                            position={Position.Left}
                            id="tools"
                            className="ab-flow-handle ab-flow-handle-tools"
                            style={{ top: topFor('tools') }}
                        />
                    )}
                    <Handle
                        type="source"
                        position={Position.Right}
                        id="toolset"
                        className="ab-flow-handle ab-flow-handle-toolset"
                        style={{ top: topFor('toolset') }}
                    />
                </>
            );
        }

        if (!expanded) {
            return (
                <>
                    <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
                    {showToolsTarget && (
                        <Handle
                            type="target"
                            position={Position.Left}
                            id="tools"
                            className="ab-flow-handle ab-flow-handle-tools"
                            style={{ top: '62%' }}
                        />
                    )}
                    <Handle type="source" position={Position.Right} id="default" className="ab-flow-handle" />
                </>
            );
        }

        // Align pins to Tools / Input / Response field anchors measured from the form.
        return (
            <>
                {showToolsTarget && (
                    <Handle
                        type="target"
                        position={Position.Left}
                        id="tools"
                        className="ab-flow-handle ab-flow-handle-tools"
                        style={{ top: topFor('tools') }}
                    />
                )}
                <Handle
                    type="target"
                    position={Position.Left}
                    id="default"
                    className="ab-flow-handle"
                    style={{ top: topFor('input') }}
                />
                <Handle
                    type="source"
                    position={Position.Right}
                    id="default"
                    className="ab-flow-handle"
                    style={{ top: topFor('response') }}
                />
            </>
        );
    }

    return (
        <>
            <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
            <Handle type="source" position={Position.Right} id="default" className="ab-flow-handle" />
        </>
    );
}

export default function WorkflowNode({ id, data, selected }) {
    const canvasUi = useCanvasUi();
    const {
        readOnly,
        agents = [],
        tools = [],
        mcpServers = [],
        knowledgeBases = [],
        ragSearchUrlTemplate = '',
        outputClasses = [],
        providers = {},
        providerModels = {},
        defaultProvider = '',
        defaultModel = '',
    } = canvasUi;

    const accent = categoryColor(data.category);
    const executionClass = data.executionStatus ? ` ab-flow-node--${data.executionStatus}` : '';
    const isDense = DENSE_TYPES.has(data.nodeType);
    const [collapsed, setCollapsed] = useState(false);
    const [formSection, setFormSection] = useState('controls');
    // Forms stay open by default; user can collapse via toolbar.
    const expanded = !collapsed && data.nodeType !== 'start' && data.nodeType !== 'stop';
    const nodeRef = useRef(null);

    const agentMode = data.nodeType === 'agent' ? resolveAgentConfigMode(data.config || {}) : null;
    const agentToolMode = data.nodeType === 'agent' ? isToolModeEnabled(data.config || {}) : false;
    const agentName =
        data.nodeType === 'agent' && agentMode === 'existing' && data.config?.agent_id
            ? agents.find((agent) => String(agent.id) === String(data.config.agent_id))?.name
            : null;
    const agentInlineMeta =
        data.nodeType === 'agent' && agentMode === 'inline'
            ? [data.config?.provider, data.config?.model].filter(Boolean).join(' / ') || null
            : null;

    const handleTopsRevision = useMemo(
        () =>
            [
                data.nodeType,
                expanded,
                formSection,
                agentMode,
                agentToolMode,
                data.config?.structured ? '1' : '0',
                data.config?.instructions?.length || 0,
            ].join(':'),
        [
            data.nodeType,
            expanded,
            formSection,
            agentMode,
            agentToolMode,
            data.config?.structured,
            data.config?.instructions?.length,
        ],
    );

    const agentHandleTops = useAgentHandleTops(
        id,
        nodeRef,
        data.nodeType === 'agent' && expanded,
        handleTopsRevision,
    );

    const editNode = useMemo(
        () =>
            normalizeNodeForEdit({
                id,
                type: data.nodeType,
                data: data.config || {},
            }),
        [id, data.nodeType, data.config],
    );

    const syncNode = useCallback(
        (nextData) => {
            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: { id, data: nextData },
                }),
            );
        },
        [id],
    );

    const removeNode = useCallback(() => {
        window.dispatchEvent(new CustomEvent('canvas-remove-node', { detail: { id } }));
    }, [id]);

    const duplicateNode = (event) => {
        event.stopPropagation();
        window.dispatchEvent(new CustomEvent('canvas-duplicate-node', { detail: { id } }));
    };

    const openAdvanced = (event) => {
        event.stopPropagation();
        window.dispatchEvent(
            new CustomEvent('canvas-node-edit', {
                detail: {
                    id,
                    type: data.nodeType,
                    data: data.config || {},
                    section: data.nodeType === 'rag' ? 'all' : 'advanced',
                },
            }),
        );
    };

    return (
        <div
            ref={nodeRef}
            className={`ab-flow-node${selected ? ' selected' : ''}${expanded ? ' ab-flow-node--expanded' : ''}${executionClass}`}
            style={{ '--node-accent': accent }}
        >
            {!readOnly && (
                <NodeToolbar isVisible={selected} position={Position.Top} offset={8}>
                    <div className="ab-flow-node-toolbar">
                        {data.nodeType !== 'start' && data.nodeType !== 'stop' && (
                            <>
                                <button
                                    type="button"
                                    className={`ab-flow-node-toolbar-btn${formSection === 'controls' && expanded ? ' is-active' : ''}`}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setCollapsed(false);
                                        setFormSection('controls');
                                    }}
                                    title="Controls"
                                >
                                    <SlidersHorizontal className="h-3.5 w-3.5" />
                                </button>
                                {isDense && (
                                    <button
                                        type="button"
                                        className="ab-flow-node-toolbar-btn"
                                        onClick={openAdvanced}
                                        title="Advanced"
                                    >
                                        <Settings2 className="h-3.5 w-3.5" />
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="ab-flow-node-toolbar-btn"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setCollapsed((value) => !value);
                                    }}
                                    title={collapsed ? 'Expand' : 'Collapse'}
                                >
                                    {collapsed ? (
                                        <ChevronDown className="h-3.5 w-3.5" />
                                    ) : (
                                        <ChevronUp className="h-3.5 w-3.5" />
                                    )}
                                </button>
                                <button
                                    type="button"
                                    className="ab-flow-node-toolbar-btn"
                                    onClick={duplicateNode}
                                    title="Duplicate"
                                >
                                    <Copy className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    className="ab-flow-node-toolbar-btn"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        removeNode();
                                    }}
                                    title="Delete"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </>
                        )}
                    </div>
                </NodeToolbar>
            )}

            <NodeHandles
                nodeType={data.nodeType}
                config={data.config}
                expanded={expanded}
                handleTops={agentHandleTops}
            />

            <div className="ab-flow-node-accent" />
            <div className="ab-flow-node-header">
                <span className="ab-flow-node-icon">
                    <NodeTypeIcon name={data.icon} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="ab-flow-node-type">{data.nodeType}</div>
                    <div className="ab-flow-node-label">{data.label}</div>
                </div>
            </div>

            {!expanded && (
                <>
                    {data.nodeType === 'llm' && data.config?.model && (
                        <div className="ab-flow-node-meta">{data.config.model}</div>
                    )}
                    {agentName && <div className="ab-flow-node-meta">{agentName}</div>}
                    {agentInlineMeta && <div className="ab-flow-node-meta">{agentInlineMeta}</div>}
                    {data.nodeType === 'agent' && agentToolMode && (
                        <div className="ab-flow-node-handles-labels">
                            <span className="ab-flow-handle-label ab-flow-handle-label-tools">tools</span>
                            <span className="ab-flow-handle-label ab-flow-handle-label-toolset">toolset</span>
                        </div>
                    )}
                    {data.nodeType === 'agent' && !agentToolMode && (
                        <div className="ab-flow-node-handles-labels">
                            <span className="ab-flow-handle-label ab-flow-handle-label-tools">tools</span>
                        </div>
                    )}
                    {data.nodeType === 'condition' && (
                        <div className="ab-flow-node-handles-labels">
                            <span className="ab-flow-handle-label ab-flow-handle-label-true">true</span>
                            <span className="ab-flow-handle-label ab-flow-handle-label-false">false</span>
                        </div>
                    )}
                    {data.nodeType === 'loop' && (
                        <div className="ab-flow-node-handles-labels">
                            <span className="ab-flow-handle-label ab-flow-handle-label-continue">continue</span>
                            <span className="ab-flow-handle-label ab-flow-handle-label-exit">exit</span>
                        </div>
                    )}
                    {data.nodeType === 'fork' && forkBranches(data.config).length > 0 && (
                        <div className="ab-flow-node-meta">{forkBranches(data.config).join(', ')}</div>
                    )}
                </>
            )}

            {data.nodeType === 'loop' && data.loopIteration && (
                <div className="ab-flow-node-meta ab-flow-node-loop-iteration">
                    {data.loopIteration.iteration} / {data.loopIteration.maxSteps}
                </div>
            )}

            {expanded && editNode && (
                <div className="nodrag nowheel ab-flow-node-form">
                    <NodeConfigForm
                        node={editNode}
                        agents={agents}
                        tools={tools}
                        mcpServers={mcpServers}
                        knowledgeBases={knowledgeBases}
                        ragSearchUrlTemplate={ragSearchUrlTemplate}
                        outputClasses={outputClasses}
                        providers={providers}
                        providerModels={providerModels}
                        defaultProvider={defaultProvider}
                        defaultModel={defaultModel}
                        readOnly={readOnly}
                        onUpdate={readOnly ? undefined : syncNode}
                        section={isDense ? formSection : 'all'}
                        compact
                        showRemove={false}
                        showType={false}
                    />
                </div>
            )}

            <div className="ab-flow-node-footer">{data.label || data.nodeType}</div>
        </div>
    );
}
