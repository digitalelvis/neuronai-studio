import { BaseEdge, EdgeLabelRenderer, getSmoothStepPath } from '@xyflow/react';

export default function WorkflowEdge({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    data,
    label,
    style,
    markerEnd,
}) {
    const [edgePath, labelX, labelY] = getSmoothStepPath({
        sourceX,
        sourceY,
        targetX,
        targetY,
        sourcePosition,
        targetPosition,
    });

    const edgeLabel = label || data?.label;

    return (
        <>
            <BaseEdge id={id} path={edgePath} style={style} markerEnd={markerEnd} interactionWidth={20} />
            {edgeLabel && (
                <EdgeLabelRenderer>
                    <div
                        className={`ab-edge-label ab-edge-label--${edgeLabel}`}
                        style={{
                            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
                        }}
                    >
                        {edgeLabel}
                    </div>
                </EdgeLabelRenderer>
            )}
        </>
    );
}
