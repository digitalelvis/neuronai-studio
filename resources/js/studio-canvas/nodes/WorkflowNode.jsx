import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { Handle, NodeToolbar, Position, useUpdateNodeInternals } from '@xyflow/react';
import { Copy, Trash2 } from 'lucide-react';
import { useCanvasUi } from '../CanvasUiContext';
import { categoryColor } from '../graph';
import { isToolModeEnabled } from '../inspector/nodeUtils';
import NodePreviewBody, { getForkBranches, getIntentIds, getSwitchCaseIds } from './NodePreviewBody';
import { NodeTypeIcon } from './nodeIcons';

const AGENT_HANDLE_FALLBACKS = {
    tools: '42%',
    input: '28%',
    response: '72%',
    toolset: '62%',
};

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

function useMeasuredHandleTops(nodeId, nodeRef, enabled, names, revision) {
    const updateNodeInternals = useUpdateNodeInternals();
    const [tops, setTops] = useState(null);
    const topsRef = useRef(null);
    const namesKey = names.join('|');

    useLayoutEffect(() => {
        if (!enabled || names.length === 0) {
            topsRef.current = null;
            setTops(null);
            return undefined;
        }

        const nodeEl = nodeRef.current;
        if (!nodeEl) {
            return undefined;
        }

        const sync = () => {
            const next = {};
            for (const name of names) {
                next[name] = measureHandleAnchor(nodeEl, name);
            }

            const prev = topsRef.current;
            const changed =
                !prev ||
                names.some((name) => prev[name] !== next[name]) ||
                Object.keys(prev).length !== names.length;

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
    }, [enabled, nodeId, nodeRef, namesKey, revision, updateNodeInternals, names]);

    return tops;
}

function FlowHandle({ type, position, id, className = '', style }) {
    return (
        <Handle type={type} position={position} id={id} className={`ab-flow-handle ${className}`.trim()} style={style}>
            <span className="ab-flow-handle-plus" aria-hidden="true">
                +
            </span>
        </Handle>
    );
}

function NodeHandles({ nodeType, config, handleTops = null }) {
    const topFor = (name, fallback) => handleTops?.[name] || fallback;

    if (nodeType === 'start') {
        return <FlowHandle type="source" position={Position.Right} id="default" />;
    }

    if (nodeType === 'stop') {
        return <FlowHandle type="target" position={Position.Left} id="default" />;
    }

    if (nodeType === 'condition') {
        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="true"
                    className="ab-flow-handle-true"
                    style={{ top: topFor('true', '40%') }}
                />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="false"
                    className="ab-flow-handle-false"
                    style={{ top: topFor('false', '70%') }}
                />
            </>
        );
    }

    if (nodeType === 'fork') {
        const branches = getForkBranches(config);

        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="default"
                    style={{ top: topFor('default', '28%') }}
                />
                {branches.map((branchId, index) => (
                    <FlowHandle
                        key={branchId}
                        type="source"
                        position={Position.Right}
                        id={branchId}
                        style={{
                            top:
                                topFor(`branch:${branchId}`) ||
                                `${35 + ((index + 1) / (branches.length + 1)) * 50}%`,
                        }}
                    />
                ))}
            </>
        );
    }

    if (nodeType === 'intent_classifier') {
        const intents = getIntentIds(config);
        const count = Math.max(intents.length, 1);

        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                {intents.map((intentId, index) => (
                    <FlowHandle
                        key={intentId}
                        type="source"
                        position={Position.Right}
                        id={intentId}
                        style={{
                            top:
                                topFor(`intent:${intentId}`) ||
                                topFor(intentId) ||
                                `${22 + ((index + 1) / (count + 1)) * 60}%`,
                        }}
                    />
                ))}
            </>
        );
    }

    if (nodeType === 'switch') {
        const cases = getSwitchCaseIds(config);
        const count = Math.max(cases.length, 1);

        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="default"
                    style={{ top: topFor('default', '22%') }}
                />
                {cases.map((caseId, index) => (
                    <FlowHandle
                        key={caseId}
                        type="source"
                        position={Position.Right}
                        id={caseId}
                        style={{
                            top:
                                topFor(`case:${caseId}`) ||
                                topFor(caseId) ||
                                `${28 + ((index + 1) / (count + 1)) * 60}%`,
                        }}
                    />
                ))}
            </>
        );
    }

    if (nodeType === 'loop') {
        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="continue"
                    className="ab-flow-handle-continue"
                    style={{ top: topFor('continue', '40%') }}
                />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="exit"
                    className="ab-flow-handle-exit"
                    style={{ top: topFor('exit', '70%') }}
                />
            </>
        );
    }

    if (nodeType === 'agent') {
        const toolMode = isToolModeEnabled(config || {});

        if (toolMode) {
            return (
                <>
                    <FlowHandle
                        type="target"
                        position={Position.Left}
                        id="tools"
                        className="ab-flow-handle-tools"
                        style={{ top: topFor('tools', AGENT_HANDLE_FALLBACKS.tools) }}
                    />
                    <FlowHandle
                        type="source"
                        position={Position.Right}
                        id="toolset"
                        className="ab-flow-handle-toolset"
                        style={{ top: topFor('toolset', AGENT_HANDLE_FALLBACKS.toolset) }}
                    />
                </>
            );
        }

        return (
            <>
                <FlowHandle
                    type="target"
                    position={Position.Left}
                    id="default"
                    style={{ top: topFor('input', AGENT_HANDLE_FALLBACKS.input) }}
                />
                <FlowHandle
                    type="target"
                    position={Position.Left}
                    id="tools"
                    className="ab-flow-handle-tools"
                    style={{ top: topFor('tools', AGENT_HANDLE_FALLBACKS.tools) }}
                />
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="default"
                    style={{ top: topFor('response', AGENT_HANDLE_FALLBACKS.response) }}
                />
            </>
        );
    }

    if (nodeType === 'run_workflow') {
        const toolMode = isToolModeEnabled(config || {});

        if (toolMode) {
            return (
                <FlowHandle
                    type="source"
                    position={Position.Right}
                    id="toolset"
                    className="ab-flow-handle-toolset"
                    style={{ top: topFor('toolset', '55%') }}
                />
            );
        }

        return (
            <>
                <FlowHandle type="target" position={Position.Left} id="default" />
                <FlowHandle type="source" position={Position.Right} id="default" />
            </>
        );
    }

    return (
        <>
            <FlowHandle type="target" position={Position.Left} id="default" />
            <FlowHandle type="source" position={Position.Right} id="default" />
        </>
    );
}

function handleNamesForNode(nodeType, config) {
    if (nodeType === 'condition') {
        return ['true', 'false'];
    }
    if (nodeType === 'loop') {
        return ['continue', 'exit'];
    }
    if (nodeType === 'fork') {
        return ['default', ...getForkBranches(config).map((id) => `branch:${id}`)];
    }
    if (nodeType === 'intent_classifier') {
        return getIntentIds(config).map((id) => `intent:${id}`);
    }
    if (nodeType === 'switch') {
        return ['default', ...getSwitchCaseIds(config).map((id) => `case:${id}`)];
    }
    if (nodeType === 'agent') {
        if (isToolModeEnabled(config || {})) {
            return ['tools', 'toolset'];
        }
        return ['input', 'tools', 'response'];
    }
    if (nodeType === 'run_workflow' && isToolModeEnabled(config || {})) {
        return ['toolset'];
    }
    return [];
}

export default function WorkflowNode({ id, data, selected }) {
    const canvasUi = useCanvasUi();
    const {
        readOnly,
        agents = [],
        workflows = [],
        tools = [],
        mcpServers = [],
        knowledgeBases = [],
    } = canvasUi;

    const accent = categoryColor(data.category);
    const executionClass = data.executionStatus ? ` ab-flow-node--${data.executionStatus}` : '';
    const nodeRef = useRef(null);

    const measureNames = useMemo(
        () => handleNamesForNode(data.nodeType, data.config),
        [data.nodeType, data.config],
    );

    const handleTopsRevision = useMemo(
        () =>
            [
                data.nodeType,
                measureNames.join('|'),
                data.config?.tool_mode ? '1' : '0',
                Array.isArray(data.config?.intents) ? data.config.intents.length : 0,
                Array.isArray(data.config?.cases) ? data.config.cases.length : 0,
                Array.isArray(data.config?.branches) ? data.config.branches.length : 0,
            ].join(':'),
        [data.nodeType, measureNames, data.config],
    );

    const handleTops = useMeasuredHandleTops(
        id,
        nodeRef,
        measureNames.length > 0,
        measureNames,
        handleTopsRevision,
    );

    const removeNode = useCallback(() => {
        window.dispatchEvent(new CustomEvent('canvas-remove-node', { detail: { id } }));
    }, [id]);

    const duplicateNode = (event) => {
        event.stopPropagation();
        window.dispatchEvent(new CustomEvent('canvas-duplicate-node', { detail: { id } }));
    };

    return (
        <div
            ref={nodeRef}
            className={`ab-flow-node${selected ? ' selected' : ''}${executionClass}`}
            style={{ '--node-accent': accent }}
        >
            {!readOnly && data.nodeType !== 'start' && data.nodeType !== 'stop' && (
                <NodeToolbar isVisible={selected} position={Position.Top} offset={8}>
                    <div className="ab-flow-node-toolbar">
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
                    </div>
                </NodeToolbar>
            )}

            <NodeHandles nodeType={data.nodeType} config={data.config} handleTops={handleTops} />

            <div className="ab-flow-node-header">
                <span className="ab-flow-node-icon">
                    <NodeTypeIcon name={data.icon} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="ab-flow-node-type">{data.nodeType}</div>
                    <div className="ab-flow-node-label">{data.title?.trim() || data.label}</div>
                </div>
            </div>

            <NodePreviewBody
                nodeType={data.nodeType}
                config={data.config}
                agents={agents}
                workflows={workflows}
                tools={tools}
                knowledgeBases={knowledgeBases}
                mcpServers={mcpServers}
                loopIteration={data.loopIteration}
            />
        </div>
    );
}
