import { Label } from '@/components/ui/label';
import { StateVariableTextField } from '../shared/state-variables';

export default function StopNodeFields({
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
        <div className="space-y-2">
            <Label>Reply</Label>
            <StateVariableTextField
                rows={compact ? 2 : 3}
                value={data.reply ?? ''}
                onChange={(e) => updateField('reply', e.target.value)}
                currentNodeId={node.id}
                placeholder="{{agent_response}}"
                disabled={readOnly}
                compact={compact}
                label="Edit reply"
            />
            <p className="text-[10px] text-muted-foreground">
                Template for the user-facing message (e.g. {'{{agent_response}}'}). Written to{' '}
                <code className="font-mono">state.reply</code> when this Stop runs.
            </p>
        </div>
    );
}
