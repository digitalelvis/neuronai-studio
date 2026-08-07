import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';
import { Checkbox } from '@/components/ui/checkbox';
import ProviderModelFields from '../ProviderModelFields';
import VisionToggleField from '../shared/VisionToggleField';
import { StateVariableTextField } from '../shared/state-variables';
import IntentEditor from './editors/IntentEditor';
import ApiKeyField from './fields/ApiKeyField';
import OutputKeyField from './fields/OutputKeyField';

export default function IntentClassifierNodeFields({
    node,
    data,
    providers = {},
    providerModels = {},
    variables = [],
    defaultProvider = '',
    defaultModel = '',
    readOnly = false,
    compact = false,
    showControls = true,
    onUpdate,
}) {
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    if (!showControls) {
        return null;
    }

    return (
        <>
            <ProviderModelFields
                provider={data.provider}
                model={data.model}
                providers={providers}
                providerModels={providerModels}
                defaultProvider={defaultProvider}
                defaultModel={defaultModel}
                readOnly={readOnly}
                onChange={(patch) => onUpdate?.({ ...data, ...patch })}
            />
            <ApiKeyField
                value={data.api_key}
                onChange={(value) => updateField('api_key', value)}
                variables={variables}
                readOnly={readOnly}
            />
            <div className="space-y-2">
                <Label>Message</Label>
                <StateVariableTextField
                    rows={compact ? 2 : 3}
                    value={data.message ?? '{{input}}'}
                    onChange={(e) => updateField('message', e.target.value)}
                    currentNodeId={node.id}
                    disabled={readOnly}
                    compact={compact}
                    label="Edit message"
                />
            </div>
            <OutputKeyField
                value={data.output_key}
                defaultValue="intent"
                onChange={(value) => updateField('output_key', value)}
                readOnly={readOnly}
            />
            <IntentEditor data={data} readOnly={readOnly} onUpdate={onUpdate} />
            <div className="space-y-2">
                <Label>Instructions</Label>
                <p className="text-xs text-muted-foreground">
                    Optional guidance to help the model choose the right intent.
                </p>
                <ExpandableTextField
                    rows={compact ? 2 : 3}
                    value={data.instructions ?? ''}
                    onChange={(e) => updateField('instructions', e.target.value)}
                    disabled={readOnly}
                    label="Edit instructions"
                    placeholder="e.g. Prefer how_to when the user asks about features or setup…"
                />
            </div>
            <VisionToggleField
                vision={Boolean(data.vision)}
                readOnly={readOnly}
                onChange={(patch) => onUpdate?.({ ...data, ...patch })}
            />
            <div className="space-y-2 rounded-md border border-border bg-muted/20 p-3">
                <div className="flex items-center justify-between gap-3">
                    <div className="space-y-0.5">
                        <Label htmlFor="intent-memory-toggle">Memory</Label>
                        <p className="text-xs text-muted-foreground">
                            Include prior conversation turns when classifying.
                            Metering always reuses the workflow thread.
                        </p>
                    </div>
                    <Checkbox
                        id="intent-memory-toggle"
                        checked={Boolean(data.memory)}
                        onCheckedChange={(checked) =>
                            onUpdate?.({ ...data, memory: checked === true })
                        }
                        disabled={readOnly}
                    />
                </div>
                {Boolean(data.memory) && (
                    <div className="space-y-2 pt-2">
                        <Label>Context window (tokens)</Label>
                        <Input
                            type="number"
                            min={1}
                            value={data.memory_config?.context_window ?? ''}
                            onChange={(e) => {
                                const value =
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value);
                                onUpdate?.({
                                    ...data,
                                    memory_config: {
                                        ...(data.memory_config || {}),
                                        context_window: value,
                                    },
                                });
                            }}
                            disabled={readOnly}
                            placeholder="Inherit default"
                        />
                    </div>
                )}
            </div>
        </>
    );
}
