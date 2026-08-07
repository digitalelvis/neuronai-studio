import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StateVariableTextField } from '../../shared/state-variables';

function normalizeStateMap(stateMap) {
    if (!Array.isArray(stateMap)) {
        return [];
    }

    return stateMap.map((row) => ({
        key: typeof row?.key === 'string' ? row.key : '',
        value: typeof row?.value === 'string' ? row.value : String(row?.value ?? ''),
    }));
}

export default function StateMapEditor({ data, readOnly, onUpdate, compact = false, currentNodeId = null }) {
    const rows = normalizeStateMap(data.state_map);

    const commit = (next) => {
        onUpdate?.({ ...data, state_map: next });
    };

    const addRow = () => {
        commit([...rows, { key: '', value: '' }]);
    };

    const updateRow = (index, patch) => {
        commit(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const removeRow = (index) => {
        commit(rows.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <Label>State map</Label>
                {!readOnly && (
                    <Button type="button" size="sm" variant="outline" className="h-7 px-2 text-[11px]" onClick={addRow}>
                        Add
                    </Button>
                )}
            </div>
            {!compact && (
                <p className="text-xs text-muted-foreground">
                    Extra keys passed into the child workflow state. Values support {'{{templates}}'}.
                </p>
            )}
            {rows.length === 0 && (
                <p className="text-xs text-muted-foreground">No state rows yet.</p>
            )}
            {rows.map((row, index) => (
                <div key={`state-map-${index}`} className="flex items-start gap-1.5">
                    <Input
                        className="h-8"
                        value={row.key}
                        onChange={(e) => updateRow(index, { key: e.target.value })}
                        placeholder="key"
                        disabled={readOnly}
                    />
                    <div className="min-w-0 flex-1">
                        <StateVariableTextField
                            value={row.value}
                            onChange={(e) => updateRow(index, { value: e.target.value })}
                            currentNodeId={currentNodeId}
                            placeholder="{{value}}"
                            disabled={readOnly}
                            compact
                            rows={1}
                            label="Edit state map value"
                            className="[&_.ab-state-var-editor]:min-h-8 [&_.ab-state-var-editor]:py-1.5 [&_.ab-state-var-editor]:pb-7"
                        />
                    </div>
                    {!readOnly && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-8 px-2 text-muted-foreground"
                            onClick={() => removeRow(index)}
                        >
                            ×
                        </Button>
                    )}
                </div>
            ))}
        </div>
    );
}
