import { useCallback, useMemo, useState } from 'react';
import { Handle, NodeToolbar, Position } from '@xyflow/react';
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
import { normalizeNodeForEdit } from '../inspector/nodeUtils';
import NodeConfigForm from '../inspector/NodeConfigForm';
import { NodeTypeIcon } from './nodeIcons';

const DENSE_TYPES = new Set(['agent', 'llm', 'rag', 'mcp', 'tool']);

function forkBranches(config) {
    if (!config || !Array.isArray(config.branches)) {
        return [];
    }

    return config.branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

function NodeHandles({ nodeType, config }) {
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

    const agentName =
        data.nodeType === 'agent' && data.config?.agent_id
            ? agents.find((agent) => String(agent.id) === String(data.config.agent_id))?.name
            : null;

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

            <NodeHandles nodeType={data.nodeType} config={data.config} />

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
