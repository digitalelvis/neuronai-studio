import { Label } from '@/components/ui/label';
import { StateVariableTextField } from '../shared/state-variables';
import OutputKeyField from './fields/OutputKeyField';

export default function HumanNodeFields({
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

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <>
            <div className="space-y-2">
                <Label>Prompt</Label>
                <StateVariableTextField
                    rows={3}
                    value={data.prompt ?? ''}
                    onChange={(e) => updateField('prompt', e.target.value)}
                    currentNodeId={node.id}
                    placeholder="Ask the user for input…"
                    disabled={readOnly}
                    compact={compact}
                    label="Edit prompt"
                />
            </div>
            <OutputKeyField
                value={data.output_key}
                defaultValue="human_response"
                onChange={(value) => updateField('output_key', value)}
                readOnly={readOnly}
            />
        </>
    );
}
