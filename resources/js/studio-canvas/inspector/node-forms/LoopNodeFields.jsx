import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StateVariableSelect } from '../shared/state-variables';
import ConditionOperatorFields from './fields/ConditionOperatorFields';

export default function LoopNodeFields({
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
                <Label>Max Steps</Label>
                <Input
                    type="number"
                    min={1}
                    value={data.max_steps ?? 10}
                    onChange={(e) => updateField('max_steps', Number(e.target.value))}
                    disabled={readOnly}
                />
                {!compact && (
                    <p className="text-xs text-muted-foreground">
                        Maximum iterations before the loop exits with an error.
                    </p>
                )}
            </div>
            <div className="space-y-2">
                <Label>Exit Condition — State Key</Label>
                <StateVariableSelect
                    value={data.state_key ?? 'input'}
                    onChange={(key) => updateField('state_key', key)}
                    currentNodeId={node.id}
                    disabled={readOnly}
                />
            </div>
            <ConditionOperatorFields
                operator={data.operator}
                value={data.value}
                onOperatorChange={(value) => updateField('operator', value)}
                onValueChange={(value) => updateField('value', value)}
                readOnly={readOnly}
                operatorLabel="Exit Condition — Operator"
                valueLabel="Exit Condition — Value"
            />
        </>
    );
}
