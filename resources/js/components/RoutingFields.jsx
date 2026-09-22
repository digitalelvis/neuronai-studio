import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ProviderModelFields from '@/studio-canvas/inspector/ProviderModelFields';
import VariableInput from '@/studio-forms/VariableInput';

const TIER_LABELS = {
    easy: 'Easy',
    medium: 'Medium',
    hard: 'Hard',
};

function emptyRouting(fallbackProvider, fallbackModel) {
    return {
        enabled: true,
        classifier_api_key: '',
        easy_max: 0.33,
        medium_max: 0.7,
        tiers: {
            hard: {
                provider: fallbackProvider || '',
                model: fallbackModel || '',
                api_key: '',
            },
        },
    };
}

export default function RoutingFields({
    value,
    onChange,
    providers = {},
    providerModels = {},
    variables = [],
    fallbackProvider = '',
    fallbackModel = '',
    readOnly = false,
    compact = false,
}) {
    const routing = value && typeof value === 'object' ? value : null;
    const enabled = routing?.enabled === true;
    const tiers = routing?.tiers && typeof routing.tiers === 'object' ? routing.tiers : {};

    const update = (patch) => {
        const base = enabled ? routing : emptyRouting(fallbackProvider, fallbackModel);
        onChange?.({ ...base, ...patch, enabled: true });
    };

    const setEnabled = (next) => {
        if (!next) {
            onChange?.(null);
            return;
        }
        onChange?.(routing?.enabled ? { ...routing, enabled: true } : emptyRouting(fallbackProvider, fallbackModel));
    };

    const setTierEnabled = (name, next) => {
        const nextTiers = { ...tiers };
        if (!next) {
            delete nextTiers[name];
        } else {
            nextTiers[name] = tiers[name] ?? {
                provider: fallbackProvider || '',
                model: fallbackModel || '',
                api_key: '',
            };
        }
        update({ tiers: nextTiers });
    };

    return (
        <div className="space-y-3 border-t border-border pt-4">
            <Label className="flex items-center gap-2">
                <Checkbox
                    checked={enabled}
                    onCheckedChange={(checked) => setEnabled(checked === true)}
                    disabled={readOnly}
                />
                Route by difficulty (JEV)
            </Label>
            <p className="text-xs text-muted-foreground">
                Classify each turn with the configured System One classifier (TypeSafe or Laya) and send it to a smaller or stronger model.
                TypeSafe needs TYPESAFE_KEY. Laya uses LAYA_URL and an optional LAYA_KEY. Leave off to keep a single model.
            </p>
            {enabled && (
                <div className="space-y-3">
                    <div className="space-y-2">
                        <Label>Classifier API key</Label>
                        <VariableInput
                            value={routing.classifier_api_key ?? ''}
                            onChange={(next) => update({ classifier_api_key: next })}
                            variables={variables}
                            sensitive
                            disabled={readOnly}
                            placeholder=""
                            hint="Optional var:NAME. Empty uses TYPESAFE_KEY or LAYA_KEY from the install."
                        />
                    </div>
                    <div className={`grid gap-3 ${compact ? 'grid-cols-1' : 'md:grid-cols-2'}`}>
                        <div className="space-y-2">
                            <Label>Easy max score</Label>
                            <Input
                                type="number"
                                min={0}
                                max={1}
                                step={0.01}
                                value={routing.easy_max ?? 0.33}
                                onChange={(e) => update({ easy_max: e.target.value === '' ? 0.33 : Number(e.target.value) })}
                                disabled={readOnly}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Medium max score</Label>
                            <Input
                                type="number"
                                min={0}
                                max={1}
                                step={0.01}
                                value={routing.medium_max ?? 0.7}
                                onChange={(e) => update({ medium_max: e.target.value === '' ? 0.7 : Number(e.target.value) })}
                                disabled={readOnly}
                            />
                        </div>
                    </div>
                    {Object.keys(TIER_LABELS).map((name) => {
                        const tier = tiers[name];
                        const on = Boolean(tier);
                        return (
                            <div key={name} className="space-y-2 rounded-md border border-border p-3">
                                <Label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={on}
                                        onCheckedChange={(checked) => setTierEnabled(name, checked === true)}
                                        disabled={readOnly}
                                    />
                                    {TIER_LABELS[name]}
                                </Label>
                                {on && (
                                    <ProviderModelFields
                                        provider={tier.provider}
                                        model={tier.model}
                                        providers={providers}
                                        providerModels={providerModels}
                                        defaultProvider={fallbackProvider}
                                        defaultModel={fallbackModel}
                                        readOnly={readOnly}
                                        onChange={(patch) => update({
                                            tiers: {
                                                ...tiers,
                                                [name]: { ...tier, ...patch },
                                            },
                                        })}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
