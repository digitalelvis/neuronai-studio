import { Label } from '@/components/ui/label';
import VariableInput from '@/studio-forms/VariableInput';

export default function ApiKeyField({
    value,
    onChange,
    variables = [],
    readOnly = false,
    label = 'API Key (optional override)',
    hint = 'Bind a Credential variable (var:NAME) or leave empty for install-time config.',
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <VariableInput
                value={value ?? ''}
                onChange={onChange}
                variables={variables}
                sensitive
                disabled={readOnly}
                placeholder=""
                hint={hint}
            />
        </div>
    );
}
