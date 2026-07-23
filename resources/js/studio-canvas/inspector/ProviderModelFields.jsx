import { Label } from '@/components/ui/label';
import { Combobox } from '@/components/ui/combobox';

export default function ProviderModelFields({
    provider,
    model,
    providers = {},
    providerModels = {},
    defaultProvider = '',
    defaultModel = '',
    readOnly = false,
    onChange,
}) {
    const currentProvider = provider || defaultProvider;
    const models = providerModels[currentProvider] ?? [];
    const currentModel = model || defaultModel || models[0] || '';

    const providerOptions = Object.entries(providers).map(([key, label]) => ({
        value: key,
        label: typeof label === 'string' ? label : key,
    }));

    const modelOptions = models.map((item) => ({
        value: item,
        label: item,
    }));

    const handleProviderChange = (value) => {
        const nextModels = providerModels[value] ?? [];
        const nextModel = nextModels.includes(currentModel) ? currentModel : (nextModels[0] ?? '');

        onChange?.({ provider: value, model: nextModel });
    };

    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <Label>Model Provider</Label>
                <Combobox
                    options={providerOptions}
                    value={currentProvider}
                    onValueChange={handleProviderChange}
                    placeholder="Select provider"
                    searchPlaceholder="Search providers…"
                    disabled={readOnly}
                />
            </div>
            <div className="space-y-2">
                <Label>Model</Label>
                <Combobox
                    options={modelOptions}
                    value={currentModel}
                    onValueChange={(value) => onChange?.({ model: value })}
                    placeholder="Select model"
                    searchPlaceholder="Search models…"
                    disabled={readOnly || models.length === 0}
                />
            </div>
        </div>
    );
}
