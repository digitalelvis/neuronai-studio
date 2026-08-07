import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OutputKeyField from './fields/OutputKeyField';

export default function InvokeNodeFields({
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
                <Label>Hook class (FQCN)</Label>
                <Input
                    value={data.hook_class ?? ''}
                    onChange={(e) => updateField('hook_class', e.target.value)}
                    placeholder="App\Neuron\Hooks\EnrichLead"
                    disabled={readOnly}
                />
                {!compact && (
                    <p className="text-xs text-muted-foreground">
                        Must be listed in <code>neuronai-studio.invoke_hooks</code> and implement{' '}
                        <code>__invoke(WorkflowState)</code>.
                    </p>
                )}
            </div>
            <OutputKeyField
                value={data.output_key}
                defaultValue="invoke_result"
                onChange={(value) => updateField('output_key', value)}
                readOnly={readOnly}
            />
        </>
    );
}
