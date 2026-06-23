import { Handle, Position } from '@xyflow/react';
import { categoryColor } from '../graph';

const ICONS = {
    play: '▶',
    stop: '■',
    bot: '🤖',
    'message-square': '💬',
    'git-branch': '⑂',
    database: '⛁',
    wrench: '🔧',
    search: '🔍',
    clock: '⏱',
    circle: '●',
};

function NodeHandles({ nodeType }) {
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

    return (
        <>
            <Handle type="target" position={Position.Left} id="default" className="ab-flow-handle" />
            <Handle type="source" position={Position.Right} id="default" className="ab-flow-handle" />
        </>
    );
}

export default function WorkflowNode({ data, selected }) {
    const accent = categoryColor(data.category);
    const icon = ICONS[data.icon] || ICONS.circle;
    const executionClass = data.executionStatus ? ` ab-flow-node--${data.executionStatus}` : '';

    return (
        <div
            className={`ab-flow-node${selected ? ' selected' : ''}${executionClass}`}
            style={{ '--node-accent': accent }}
        >
            <NodeHandles nodeType={data.nodeType} />
            <div className="ab-flow-node-accent" />
            <div className="ab-flow-node-header">
                <span className="ab-flow-node-icon">{icon}</span>
                <span className="ab-flow-node-type">{data.nodeType}</span>
            </div>
            <div className="ab-flow-node-label">{data.label}</div>
            {data.nodeType === 'condition' && (
                <div className="ab-flow-node-handles-labels">
                    <span className="ab-flow-handle-label ab-flow-handle-label-true">true</span>
                    <span className="ab-flow-handle-label ab-flow-handle-label-false">false</span>
                </div>
            )}
        </div>
    );
}
