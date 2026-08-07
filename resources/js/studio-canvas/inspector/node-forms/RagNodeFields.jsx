import RagFields from '../shared/RagFields';

export default function RagNodeFields({
    node,
    data,
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    readOnly = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    return (
        <RagFields
            data={data}
            knowledgeBases={knowledgeBases}
            ragSearchUrlTemplate={ragSearchUrlTemplate}
            readOnly={readOnly}
            currentNodeId={node.id}
            onChange={(patch) => onUpdate?.({ ...data, ...patch })}
        />
    );
}
