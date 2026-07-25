import { BaseEdge, EdgeLabelRenderer, getBezierPath } from '@xyflow/react';

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
    selected,
}) {
    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX,
        sourceY,
        targetX,
        targetY,
        sourcePosition,
        targetPosition,
        curvature: 0.25,
    });

    const edgeLabel = label || data?.label;

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                style={style}
                markerEnd={markerEnd}
                interactionWidth={24}
                className={selected ? 'ab-edge-path ab-edge-path--selected' : 'ab-edge-path'}
            />
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
