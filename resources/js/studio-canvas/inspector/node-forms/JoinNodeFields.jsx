import OutputKeyField from './fields/OutputKeyField';

export default function JoinNodeFields({
    data,
    readOnly = false,
    compact = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <OutputKeyField
            value={data.output_key}
            defaultValue="parallel_results"
            onChange={(value) => updateField('output_key', value)}
            readOnly={readOnly}
            compact={compact}
            hint={
                <>
                    State key that receives the merged branch results, keyed by branch id
                    (e.g. {'{ branch_a: …, branch_b: … }'}).
                </>
            }
        />
    );
}
