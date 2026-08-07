import { Label } from '@/components/ui/label';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';

export default function ParametersJsonField({ data, readOnly = false, onUpdate }) {
    const updateParametersJson = (json) => {
        try {
            const parameters = JSON.parse(json || '{}');
            onUpdate?.({ ...data, parameters_json: json, parameters });
        } catch {
            onUpdate?.({ ...data, parameters_json: json });
        }
    };

    return (
        <div className="space-y-2">
            <Label>Parameters JSON</Label>
            <ExpandableTextField
                rows={3}
                value={data.parameters_json ?? (data.parameters ? JSON.stringify(data.parameters, null, 2) : '')}
                onChange={(e) => updateParametersJson(e.target.value)}
                placeholder='{"query": "$input"}'
                disabled={readOnly}
                className="font-mono text-xs"
                label="Edit text content"
            />
        </div>
    );
}
