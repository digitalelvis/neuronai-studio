import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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

    const handleProviderChange = (value) => {
        const nextModels = providerModels[value] ?? [];
        const nextModel = nextModels.includes(currentModel) ? currentModel : (nextModels[0] ?? '');

        onChange?.({ provider: value, model: nextModel });
    };

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
                <Label>Provider</Label>
                <Select value={currentProvider} onValueChange={handleProviderChange} disabled={readOnly}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select provider" />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(providers).map(([key, label]) => (
                            <SelectItem key={key} value={key}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label>Model</Label>
                <Select
                    value={currentModel}
                    onValueChange={(value) => onChange?.({ model: value })}
                    disabled={readOnly || models.length === 0}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select model" />
                    </SelectTrigger>
                    <SelectContent>
                        {models.map((item) => (
                            <SelectItem key={item} value={item}>
                                {item}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
