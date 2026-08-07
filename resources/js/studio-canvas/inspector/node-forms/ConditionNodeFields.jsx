import { Label } from '@/components/ui/label';
import { StateVariableSelect } from '../shared/state-variables';
import ConditionOperatorFields from './fields/ConditionOperatorFields';

export default function ConditionNodeFields({
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
                <Label>State Key</Label>
                <StateVariableSelect
                    value={data.state_key ?? 'input'}
                    onChange={(key) => updateField('state_key', key)}
                    currentNodeId={node.id}
                    disabled={readOnly}
                />
                {!compact && (
                    <p className="text-xs text-muted-foreground">
                        Key in workflow state. Use dot notation for nested values (e.g. lead.tier).
                    </p>
                )}
            </div>
            <ConditionOperatorFields
                operator={data.operator}
                value={data.value}
                onOperatorChange={(value) => updateField('operator', value)}
                onValueChange={(value) => updateField('value', value)}
                readOnly={readOnly}
            />
        </>
    );
}
