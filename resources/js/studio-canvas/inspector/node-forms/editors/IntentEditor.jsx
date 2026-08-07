import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';

function slugifyIntentId(value) {
    const slug = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');

    if (!slug) {
        return 'intent';
    }

    return /^[a-z]/.test(slug) ? slug : `intent_${slug}`;
}

function normalizeIntents(intents) {
    if (!Array.isArray(intents)) {
        return [];
    }

    return intents
        .filter((item) => item && typeof item === 'object')
        .map((item, index) => {
            const id =
                typeof item.id === 'string' && item.id !== ''
                    ? item.id
                    : `intent_${index + 1}`;
            return {
                id,
                name: typeof item.name === 'string' && item.name !== '' ? item.name : id,
                description: typeof item.description === 'string' ? item.description : '',
            };
        });
}

export default function IntentEditor({ data, readOnly, onUpdate }) {
    const intents = normalizeIntents(data.intents);

    const commit = (next) => {
        onUpdate?.({ ...data, intents: next });
    };

    const addIntent = () => {
        const nextIndex = intents.length + 1;
        commit([
            ...intents,
            {
                id: `intent_${nextIndex}`,
                name: `Intent ${nextIndex}`,
                description: '',
            },
        ]);
    };

    const updateIntent = (index, patch) => {
        commit(intents.map((intent, i) => (i === index ? { ...intent, ...patch } : intent)));
    };

    const renameFromName = (index, name) => {
        updateIntent(index, { name });
    };

    const syncIdFromName = (index) => {
        const current = intents[index];
        if (!current) {
            return;
        }

        const shouldSyncId =
            !current.id || current.id === slugifyIntentId(current.name) || current.id.startsWith('intent_');
        if (!shouldSyncId) {
            return;
        }

        const nextId = slugifyIntentId(current.name);
        if (nextId === current.id) {
            return;
        }

        updateIntent(index, { id: nextId });
    };

    const removeIntent = (index) => {
        commit(intents.filter((_, i) => i !== index));
    };

    const duplicateIntent = (index) => {
        const source = intents[index];
        if (!source) {
            return;
        }
        const baseId = `${source.id}_copy`;
        let id = baseId;
        let n = 2;
        const existing = new Set(intents.map((i) => i.id));
        while (existing.has(id)) {
            id = `${baseId}_${n}`;
            n += 1;
        }
        commit([
            ...intents.slice(0, index + 1),
            { ...source, id, name: `${source.name} copy` },
            ...intents.slice(index + 1),
        ]);
    };

    return (
        <div className="space-y-3">
            <div>
                <Label>Intents</Label>
                <p className="text-xs text-muted-foreground">
                    Each intent adds an output handle. Use clear, mutually exclusive descriptions.
                    Include an Other intent for unmatched messages.
                </p>
            </div>

            {intents.map((intent, index) => (
                <div
                    key={`intent-row-${index}`}
                    className="relative space-y-2 rounded-md border border-border p-3"
                    data-ab-handle-anchor={`intent:${intent.id}`}
                >
                    <div className="flex items-center gap-2">
                        <Input
                            value={intent.name}
                            onChange={(e) => renameFromName(index, e.target.value)}
                            onBlur={() => syncIdFromName(index)}
                            disabled={readOnly}
                            placeholder="Intent name"
                        />
                        {!readOnly && (
                            <>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => duplicateIntent(index)}
                                    title="Duplicate"
                                >
                                    ⎘
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => removeIntent(index)}
                                    title="Remove"
                                    disabled={intents.length <= 2}
                                >
                                    ✕
                                </Button>
                            </>
                        )}
                    </div>
                    <Input
                        value={intent.id}
                        onChange={(e) => updateIntent(index, { id: e.target.value })}
                        disabled={readOnly}
                        className="font-mono text-xs"
                        placeholder="intent_id"
                    />
                    <ExpandableTextField
                        rows={2}
                        value={intent.description}
                        onChange={(e) => updateIntent(index, { description: e.target.value })}
                        disabled={readOnly}
                        label="Edit description"
                        placeholder="Describe when this intent applies…"
                    />
                </div>
            ))}

            {!readOnly && (
                <Button variant="outline" size="sm" onClick={addIntent}>
                    Add Intent
                </Button>
            )}
        </div>
    );
}
