import { Handle, NodeToolbar, Position } from '@xyflow/react';
import { Pencil } from 'lucide-react';
import { useCanvasUi } from '../CanvasUiContext';
import { categoryColor } from '../graph';
import { dispatchNodeEdit } from '../inspector/nodeUtils';

const ICONS = {
    play: '▶',
    stop: '■',
    bot: '🤖',
    'message-square': '💬',
    'git-branch': '⑂',
    'git-fork': '⋔',
    'git-merge': '⋈',
    database: '⛁',
    wrench: '🔧',
    search: '🔍',
    clock: '⏱',
    repeat: '↻',
    circle: '●',
};

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
                        className="ab-flow-handle ab-flow-handle-continue"
                        style={{ top: `${Math.round(40 + (index * 55) / Math.max(1, count))}%` }}
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
    const { readOnly, agents } = useCanvasUi();
    const accent = categoryColor(data.category);
    const icon = ICONS[data.icon] || ICONS.circle;
    const executionClass = data.executionStatus ? ` ab-flow-node--${data.executionStatus}` : '';
    const agentName =
        data.nodeType === 'agent' && data.config?.agent_id
            ? agents.find((agent) => String(agent.id) === String(data.config.agent_id))?.name
            : null;

    const handleEdit = (event) => {
        event.stopPropagation();
        dispatchNodeEdit({ id, data });
    };

    return (
        <div
            className={`ab-flow-node${selected ? ' selected' : ''}${executionClass}`}
            style={{ '--node-accent': accent }}
        >
            {!readOnly && (
                <NodeToolbar isVisible={selected} position={Position.Top} offset={8}>
                    <button
                        type="button"
                        className="ab-flow-node-toolbar-btn"
                        onClick={handleEdit}
                        title="Edit node"
                    >
                        <Pencil className="h-3.5 w-3.5" />
                    </button>
                </NodeToolbar>
            )}
            <NodeHandles nodeType={data.nodeType} config={data.config} />
            <div className="ab-flow-node-accent" />
            <div className="ab-flow-node-header">
                <span className="ab-flow-node-icon">{icon}</span>
                <span className="ab-flow-node-type">{data.nodeType}</span>
            </div>
            <div className="ab-flow-node-label">{data.label}</div>
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
            {data.nodeType === 'loop' && data.loopIteration && (
                <div className="ab-flow-node-meta ab-flow-node-loop-iteration">
                    {data.loopIteration.iteration} / {data.loopIteration.maxSteps}
                </div>
            )}
        </div>
    );
}
