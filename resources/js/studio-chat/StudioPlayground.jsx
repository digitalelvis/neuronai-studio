import { useCallback, useMemo, useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { loadPresets, presetStorageKey, savePresets } from './utils/presets';

export default function StudioPlayground({
    mode,
    entityId,
    context,
    onContextChange,
    variant = 'panel',
}) {
    const storageKey = useMemo(() => presetStorageKey(mode, entityId), [mode, entityId]);
    const [contextJson, setContextJson] = useState(() => JSON.stringify(context ?? {}, null, 2));
    const [jsonError, setJsonError] = useState('');
    const [presets, setPresets] = useState(() => loadPresets(storageKey));
    const [presetName, setPresetName] = useState('');

    const applyContext = useCallback(
        (value) => {
            setContextJson(value);
            try {
                const parsed = JSON.parse(value || '{}');
                setJsonError('');
                onContextChange?.(parsed);
            } catch {
                setJsonError('Invalid JSON');
            }
        },
        [onContextChange],
    );

    const savePreset = () => {
        if (!presetName.trim()) {
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

    const contextLabel = mode === 'workflow' ? 'Initial state JSON' : 'Context JSON';

    const contextEditor = (
        <>
            <Label htmlFor="playground-context">{contextLabel}</Label>
            <Textarea
                id="playground-context"
                className="mt-2 min-h-[200px] font-mono text-xs"
                value={contextJson}
                onChange={(event) => applyContext(event.target.value)}
            />
            {jsonError && <p className="mt-2 text-sm text-destructive">{jsonError}</p>}
        </>
    );

    const presetsPanel = (
        <div className="space-y-3">
            <div className="flex gap-2">
                <Input
                    placeholder="Preset name"
                    value={presetName}
                    onChange={(event) => setPresetName(event.target.value)}
                />
                <Button type="button" variant="secondary" size="sm" onClick={savePreset}>
                    Save
                </Button>
            </div>
            {presets.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {presets.map((preset) => (
                        <Button key={preset.name} type="button" variant="outline" size="sm" onClick={() => loadPreset(preset)}>
                            {preset.name}
                        </Button>
                    ))}
                </div>
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
