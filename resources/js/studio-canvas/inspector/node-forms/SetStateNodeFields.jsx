import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StateVariableTextField } from '../shared/state-variables';

export default function SetStateNodeFields({
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
                <Label>Key</Label>
                <Input
                    value={data.key ?? ''}
                    onChange={(e) => updateField('key', e.target.value)}
                    placeholder="tier"
                    disabled={readOnly}
                />
                {!compact && (
                    <p className="text-xs text-muted-foreground">
                        Target state key to write (destination).
                    </p>
                )}
            </div>
            <div className="space-y-2">
                <Label>Value</Label>
                <StateVariableTextField
                    rows={compact ? 2 : 3}
                    value={
                        data.value != null && String(data.value) !== ''
                            ? String(data.value)
                            : data.from_key
                              ? `{{${data.from_key}}}`
                              : ''
                    }
                    onChange={(e) =>
                        onUpdate?.({
                            ...data,
                            value: e.target.value,
                            from_key: null,
                            append_from_key: null,
                        })
                    }
                    currentNodeId={node.id}
                    placeholder="gold or Hello {{input}}"
                    disabled={readOnly}
                    label="Edit value"
                />
                {!compact && (
                    <p className="text-xs text-muted-foreground">
                        Literal or {'{{templates}}'} from workflow state.
                    </p>
                )}
            </div>
        </>
    );
}
