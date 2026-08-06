import { useCallback, useEffect, useMemo, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import CodeEditor from '@/components/code/CodeEditor';
import {
    deletePreset,
    loadPresets,
    presetStorageKey,
    savePresets,
} from './utils/presets';

export default function StudioPlayground({
    mode,
    entityId,
    context,
    onContextChange,
    variant = 'panel',
}) {
    const storageKey = useMemo(
        () => (entityId ? presetStorageKey(mode, entityId) : null),
        [mode, entityId],
    );
    const [contextJson, setContextJson] = useState(() => JSON.stringify(context ?? {}, null, 2));
    const [jsonError, setJsonError] = useState('');
    const [presets, setPresets] = useState(() => (storageKey ? loadPresets(storageKey) : []));
    const [presetName, setPresetName] = useState('');

    useEffect(() => {
        if (!storageKey) {
            setPresets([]);
            return;
        }

        setPresets(loadPresets(storageKey));

        const onPresetsChanged = (event) => {
            if (event.detail?.storageKey && event.detail.storageKey !== storageKey) {
                return;
            }

            setPresets(loadPresets(storageKey));
        };

        window.addEventListener('playground-presets-changed', onPresetsChanged);
        return () => window.removeEventListener('playground-presets-changed', onPresetsChanged);
    }, [storageKey]);

    useEffect(() => {
        setContextJson(JSON.stringify(context ?? {}, null, 2));
    }, [context]);

    const applyContext = useCallback(
        (value) => {
            setContextJson(value);
            try {
                const parsed = JSON.parse(value || '{}');
                if (parsed == null || typeof parsed !== 'object' || Array.isArray(parsed)) {
                    setJsonError('Initial state must be a JSON object');
                    return;
                }

                setJsonError('');
                onContextChange?.(parsed);
            } catch {
                setJsonError('Invalid JSON');
            }
        },
        [onContextChange],
    );

    const savePreset = () => {
        if (!storageKey || !presetName.trim() || jsonError) {
            return;
        }

        const next = [
            ...presets.filter((item) => item.name !== presetName.trim()),
            { name: presetName.trim(), context: contextJson },
        ];

        setPresets(next);
        savePresets(storageKey, next);
        setPresetName('');
    };

    const loadPreset = (preset) => {
        applyContext(preset.context);
    };

    const removePreset = (name) => {
        if (!storageKey) {
            return;
        }

        setPresets(deletePreset(storageKey, name));
    };

    const contextLabel = mode === 'workflow' ? 'Initial state JSON' : 'Context JSON';

    const contextEditor = (
        <>
            <Label htmlFor="playground-context">{contextLabel}</Label>
            <div className="mt-2">
                <CodeEditor
                    value={contextJson}
                    onChange={applyContext}
                    language="json"
                    minHeight="240px"
                    className="w-full"
                />
            </div>
            {jsonError && <p className="mt-2 text-sm text-destructive">{jsonError}</p>}
            {!entityId && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Save the workflow first to persist presets and context.
                </p>
            )}
        </>
    );

    const presetsPanel = (
        <div className="space-y-3">
            <div className="flex gap-2">
                <Input
                    placeholder="Preset name"
                    value={presetName}
                    onChange={(event) => setPresetName(event.target.value)}
                    disabled={!storageKey}
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={savePreset}
                    disabled={!storageKey || !presetName.trim() || Boolean(jsonError)}
                >
                    Save
                </Button>
            </div>
            {presets.length > 0 ? (
                <ul className="space-y-1.5">
                    {presets.map((preset) => (
                        <li key={preset.name} className="flex items-center gap-1.5">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="min-w-0 flex-1 justify-start"
                                onClick={() => loadPreset(preset)}
                            >
                                <span className="truncate">{preset.name}</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                onClick={() => removePreset(preset.name)}
                                title={`Delete ${preset.name}`}
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">No saved presets yet.</p>
            )}
        </div>
    );

    if (variant === 'sheet') {
        return (
            <Tabs defaultValue="context">
                <TabsList className="w-full">
                    <TabsTrigger value="context" className="flex-1">
                        Context
                    </TabsTrigger>
                    <TabsTrigger value="presets" className="flex-1">
                        Presets
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="context" className="mt-4">
                    {contextEditor}
                </TabsContent>
                <TabsContent value="presets" className="mt-4">
                    {presetsPanel}
                </TabsContent>
            </Tabs>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <h3 className="mb-3 text-sm font-medium text-muted-foreground">Inputs</h3>
            <Tabs defaultValue="context" className="flex flex-1 flex-col overflow-hidden">
                <TabsList>
                    <TabsTrigger value="context">Context</TabsTrigger>
                    <TabsTrigger value="presets">Presets</TabsTrigger>
                </TabsList>
                <TabsContent value="context" className="mt-3 flex-1 overflow-auto">
                    {contextEditor}
                </TabsContent>
                <TabsContent value="presets" className="mt-3 flex-1 overflow-auto">
                    {presetsPanel}
                </TabsContent>
            </Tabs>
        </div>
    );
}
