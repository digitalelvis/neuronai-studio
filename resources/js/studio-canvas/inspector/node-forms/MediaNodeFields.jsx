import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import ProviderModelFields from '../ProviderModelFields';
import { StateVariableTextField } from '../shared/state-variables';
import ApiKeyField from './fields/ApiKeyField';
import OutputKeyField from './fields/OutputKeyField';

const OUTPUT_DEFAULTS = {
    image: 'image_result',
    speech: 'speech_result',
    transcribe: 'transcript',
    video: 'video_result',
};

export default function MediaNodeFields({
    node,
    data,
    mediaCatalogs = {},
    variables = [],
    readOnly = false,
    compact = false,
    showControls = true,
    onUpdate,
}) {
    const kind = node?.type || 'image';
    const catalog = mediaCatalogs[kind] || {};
    const providerLabels = Object.fromEntries(
        Object.entries(catalog).map(([key, entry]) => [key, entry?.label || key]),
    );
    const providerModels = Object.fromEntries(
        Object.entries(catalog).map(([key, entry]) => [key, entry?.models || []]),
    );
    const provider = data.provider || Object.keys(catalog)[0] || '';
    const entry = catalog[provider] || {};
    const voices = entry.voices || [];

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <>
            {showControls && (
                <>
                    <ProviderModelFields
                        provider={provider}
                        model={data.model}
                        providers={providerLabels}
                        providerModels={providerModels}
                        defaultProvider={provider}
                        defaultModel={providerModels[provider]?.[0] || ''}
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
                        <Label>{kind === 'transcribe' ? 'Hint' : 'Prompt'}</Label>
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
                    {kind === 'speech' && voices.length > 0 && (
                        <div className="space-y-2">
                            <Label>Voice</Label>
                            <select
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={data.voice || entry.default_voice || voices[0]}
                                disabled={readOnly}
                                onChange={(e) => updateField('voice', e.target.value)}
                            >
                                {voices.map((voice) => (
                                    <option key={voice} value={voice}>{voice}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    {kind === 'speech' && voices.length === 0 && (
                        <div className="space-y-2">
                            <Label>Voice ID</Label>
                            <Input
                                value={data.voice ?? ''}
                                disabled={readOnly}
                                onChange={(e) => updateField('voice', e.target.value)}
                                placeholder="ElevenLabs voice id"
                            />
                        </div>
                    )}
                    {kind === 'transcribe' && provider === 'openai' && (
                        <div className="space-y-2">
                            <Label>Language</Label>
                            <Input
                                value={data.language ?? 'en'}
                                disabled={readOnly}
                                onChange={(e) => updateField('language', e.target.value)}
                            />
                        </div>
                    )}
                    {kind === 'video' && (
                        <>
                            <div className="space-y-2">
                                <Label>Aspect ratio</Label>
                                <Input
                                    value={data.aspect_ratio ?? '16:9'}
                                    disabled={readOnly}
                                    onChange={(e) => updateField('aspect_ratio', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Duration (seconds)</Label>
                                <Input
                                    type="number"
                                    value={data.duration_seconds ?? 8}
                                    disabled={readOnly}
                                    onChange={(e) => updateField('duration_seconds', e.target.value)}
                                />
                            </div>
                        </>
                    )}
                    <OutputKeyField
                        value={data.output_key}
                        defaultValue={OUTPUT_DEFAULTS[kind] || 'media_result'}
                        onChange={(value) => updateField('output_key', value)}
                        readOnly={readOnly}
                    />
                </>
            )}
        </>
    );
}
