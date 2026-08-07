import { Label } from '@/components/ui/label';
import ProviderModelFields from '../ProviderModelFields';
import StructuredOutputFields from '../shared/StructuredOutputFields';
import StreamToggleField from '../shared/StreamToggleField';
import VisionToggleField from '../shared/VisionToggleField';
import { StateVariableTextField } from '../shared/state-variables';
import ApiKeyField from './fields/ApiKeyField';
import OutputKeyField from './fields/OutputKeyField';

export default function LlmNodeFields({
    node,
    data,
    providers = {},
    providerModels = {},
    variables = [],
    outputClasses = [],
    defaultProvider = '',
    defaultModel = '',
    readOnly = false,
    compact = false,
    showControls = true,
    showAdvanced = true,
    onUpdate,
}) {
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <>
            {showControls && (
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
                        <Label>Prompt</Label>
                        <StateVariableTextField
                            rows={compact ? 3 : 4}
                            value={data.prompt ?? ''}
                            onChange={(e) => updateField('prompt', e.target.value)}
                            currentNodeId={node.id}
                            disabled={readOnly}
                            compact={compact}
                            label="Edit prompt"
                        />
                    </div>
                    <OutputKeyField
                        value={data.output_key}
                        defaultValue="llm_response"
                        onChange={(value) => updateField('output_key', value)}
                        readOnly={readOnly}
                    />
                    <StreamToggleField
                        stream={Boolean(data.stream)}
                        structured={Boolean(data.structured)}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                    <VisionToggleField
                        vision={data.vision !== false}
                        defaultOn
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                </>
            )}
            {showAdvanced && (
                <StructuredOutputFields
                    structured={Boolean(data.structured)}
                    outputClass={data.output_class ?? ''}
                    outputClasses={outputClasses}
                    readOnly={readOnly}
                    onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                />
            )}
        </>
    );
}
