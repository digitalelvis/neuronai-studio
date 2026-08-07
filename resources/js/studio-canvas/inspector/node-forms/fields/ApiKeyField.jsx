import { Label } from '@/components/ui/label';
import VariableInput from '@/studio-forms/VariableInput';

export default function ApiKeyField({ value, onChange, variables = [], readOnly = false }) {
    return (
        <div className="space-y-2">
            <Label>API Key (optional override)</Label>
            <VariableInput
                value={value ?? ''}
                onChange={onChange}
                variables={variables}
                sensitive
                disabled={readOnly}
                placeholder=""
                hint="Bind a Credential variable (var:NAME) or leave empty for install-time config."
            />
        </div>
    );
}
