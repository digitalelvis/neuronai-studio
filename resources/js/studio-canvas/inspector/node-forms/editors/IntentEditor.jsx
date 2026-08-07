import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';
import { sanitizeIntentId, uniqueIntentId } from '../../../graph';

function normalizeIntents(intents) {
    if (!Array.isArray(intents)) {
        return [];
    }

    return intents
        .filter((item) => item && typeof item === 'object')
        .map((item, index) => {
            const rawId =
                typeof item.id === 'string' && item.id !== ''
                    ? item.id
                    : `intent_${index + 1}`;
            return {
                id: rawId,
                name: typeof item.name === 'string' && item.name !== '' ? item.name : rawId,
                description: typeof item.description === 'string' ? item.description : '',
            };
        });
}

function ensureUniqueIds(intents) {
    const seen = new Set();
    return intents.map((intent) => {
        const id = uniqueIntentId(intent.id, [...seen]);
        seen.add(id);
        return { ...intent, id };
    });
}

export default function IntentEditor({ data, readOnly, onUpdate }) {
    const intents = normalizeIntents(data.intents);

    const commit = (next) => {
        onUpdate?.({ ...data, intents: ensureUniqueIds(next) });
    };

    const addIntent = () => {
        const nextIndex = intents.length + 1;
        const id = uniqueIntentId(`intent_${nextIndex}`, intents.map((i) => i.id));
        commit([
            ...intents,
            {
                id,
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

        const sanitizedId = sanitizeIntentId(current.id);
        const idIsInvalid = current.id !== sanitizedId;
        const shouldSyncId =
            !current.id ||
            idIsInvalid ||
            current.id === sanitizeIntentId(current.name) ||
            current.id.startsWith('intent_');

        if (!shouldSyncId) {
            return;
        }

        const source = idIsInvalid ? current.id : current.name;
        const nextId = uniqueIntentId(
            source,
            intents.map((i) => i.id),
            current.id,
        );

        if (nextId === current.id) {
            return;
        }

        updateIntent(index, { id: nextId });
    };

    const commitSanitizedId = (index) => {
        const current = intents[index];
        if (!current) {
            return;
        }

        const nextId = uniqueIntentId(
            current.id,
            intents.map((i) => i.id),
            current.id,
        );

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
        const id = uniqueIntentId(`${source.id}_copy`, intents.map((i) => i.id));
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
                        onBlur={() => commitSanitizedId(index)}
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
