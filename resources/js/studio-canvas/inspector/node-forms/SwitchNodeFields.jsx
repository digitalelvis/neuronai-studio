import CaseEditor from './editors/CaseEditor';

export default function SwitchNodeFields({
    node,
    data,
    readOnly = false,
    compact = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    return (
        <>
            <CaseEditor
                data={data}
                readOnly={readOnly}
                currentNodeId={node.id}
                onUpdate={onUpdate}
            />
            {!compact && (
                <p className="text-xs text-muted-foreground">
                    Evaluates cases top to bottom. Connect each case handle to a branch; use
                    default for no match.
                </p>
            )}
        </>
    );
}
