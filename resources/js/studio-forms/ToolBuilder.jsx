import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function ToolBuilder({ config }) {
    const initial = config.initial ?? {};
    const [toolKind, setToolKind] = useState(initial.toolKind ?? 'builder');
    const [name, setName] = useState(initial.name ?? '');
    const [toolName, setToolName] = useState(initial.toolName ?? '');
    const [description, setDescription] = useState(initial.description ?? '');
    const [method, setMethod] = useState(initial.method ?? 'GET');
    const [url, setUrl] = useState(initial.url ?? '');
    const [headersJson, setHeadersJson] = useState(initial.headersJson ?? '{}');
    const [invokeBody, setInvokeBody] = useState(initial.invokeBody ?? '');
    const [inputSchema, setInputSchema] = useState(initial.inputSchema ?? []);
    const [preview, setPreview] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const refreshPreview = async () => {
        if (toolKind !== 'builder') {
            setPreview('');
            return;
        }

        const component = window.Livewire?.find(config.wireId);
        if (!component) return;

        const result = await component.call('previewFromReact', {
            toolKind,
            name,
            toolName,
            description,
            invokeBody,
            inputSchema,
        });

        setPreview(result ?? '');
    };

    useEffect(() => {
        const timer = setTimeout(refreshPreview, 300);
        return () => clearTimeout(timer);
    }, [toolKind, name, toolName, description, invokeBody, inputSchema]);

    const addProperty = () => {
        setInputSchema((current) => [
            ...current,
            { name: '', type: 'string', description: '', required: false },
        ]);
    };

    const removeProperty = (index) => {
        setInputSchema((current) => current.filter((_, i) => i !== index));
    };

    const updateProperty = (index, field, value) => {
        setInputSchema((current) =>
            current.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
        );
    };

    const handleSave = async () => {
        setSaving(true);
        setError('');

        try {
            const component = window.Livewire?.find(config.wireId);
            if (!component) throw new Error('Livewire component not available.');

            await component.call('saveFromReact', {
                toolKind,
                name,
                toolName,
                description,
                method,
                url,
                headersJson,
                invokeBody,
                inputSchema,
            });
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : 'Save failed.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex h-full min-h-0 flex-col bg-background">
            <div className="shrink-0 border-b border-border px-4 py-3">
                <Tabs value={toolKind} onValueChange={setToolKind}>
                    <TabsList>
                        <TabsTrigger value="builder">PHP Class Builder</TabsTrigger>
                        <TabsTrigger value="webhook">Webhook</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                <ResizablePanel defaultSize={toolKind === 'builder' ? 55 : 100} minSize={40}>
                    <ScrollArea className="h-full p-4">
                        <div className="mx-auto max-w-2xl space-y-4">
                            <div className="space-y-2">
                                <Label>Display Name</Label>
                                <Input value={name} onChange={(e) => setName(e.target.value)} required />
                            </div>
                            {toolKind === 'builder' && (
                                <div className="space-y-2">
                                    <Label>Tool Name (function identifier)</Label>
                                    <Input
                                        value={toolName}
                                        onChange={(e) => setToolName(e.target.value)}
                                        placeholder="check_server_test"
                                    />
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label>Description</Label>
                                <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
                            </div>

                            {toolKind === 'webhook' && (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>HTTP Method</Label>
                                            <Select value={method} onValueChange={setMethod}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => (
                                                        <SelectItem key={m} value={m}>
                                                            {m}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>URL</Label>
                                            <Input value={url} onChange={(e) => setUrl(e.target.value)} type="url" />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Headers (JSON)</Label>
                                        <Textarea value={headersJson} onChange={(e) => setHeadersJson(e.target.value)} rows={4} className="font-mono text-xs" />
                                    </div>
                                </>
                            )}

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm">Properties</CardTitle>
                                    <Button type="button" variant="outline" size="sm" onClick={addProperty}>
                                        Add Property
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {inputSchema.map((property, index) => (
                                        <div key={index} className="grid gap-2 rounded-md border border-border p-3 md:grid-cols-12">
                                            <Input
                                                className="md:col-span-3"
                                                placeholder="name"
                                                value={property.name}
                                                onChange={(e) => updateProperty(index, 'name', e.target.value)}
                                            />
                                            <Select value={property.type} onValueChange={(v) => updateProperty(index, 'type', v)}>
                                                <SelectTrigger className="md:col-span-2">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {['string', 'integer', 'number', 'boolean'].map((t) => (
                                                        <SelectItem key={t} value={t}>
                                                            {t}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                className="md:col-span-4"
                                                placeholder="description"
                                                value={property.description}
                                                onChange={(e) => updateProperty(index, 'description', e.target.value)}
                                            />
                                            <label className="flex items-center gap-2 text-xs md:col-span-2">
                                                <Checkbox
                                                    checked={property.required}
                                                    onCheckedChange={(checked) => updateProperty(index, 'required', Boolean(checked))}
                                                />
                                                Required
                                            </label>
                                            <Button type="button" variant="ghost" size="sm" className="md:col-span-1 text-destructive" onClick={() => removeProperty(index)}>
                                                ×
                                            </Button>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            {toolKind === 'builder' && (
                                <div className="space-y-2">
                                    <Label>Invoke body</Label>
                                    <Textarea
                                        value={invokeBody}
                                        onChange={(e) => setInvokeBody(e.target.value)}
                                        rows={10}
                                        className="font-mono text-xs"
                                        spellCheck={false}
                                    />
                                    <p className="text-xs text-muted-foreground">Write only the method body. Parameters are generated from properties above.</p>
                                </div>
                            )}
                        </div>
                    </ScrollArea>
                </ResizablePanel>

                {toolKind === 'builder' && (
                    <>
                        <ResizableHandle withHandle />
                        <ResizablePanel defaultSize={45} minSize={25}>
                            <div className="flex h-full flex-col p-4">
                                <h3 className="mb-2 text-sm font-medium text-muted-foreground">Generated Class Preview</h3>
                                <pre className="flex-1 overflow-auto rounded-md border border-border bg-background p-4 font-mono text-xs">{preview || 'Preview will appear here…'}</pre>
                            </div>
                        </ResizablePanel>
                    </>
                )}
            </ResizablePanelGroup>

            <div className="flex shrink-0 items-center justify-between border-t border-border px-4 py-3">
                {error && <span className="text-sm text-destructive">{error}</span>}
                <div className="ml-auto flex gap-2">
                    <Button variant="outline" asChild>
                        <a href={config.cancelUrl}>Cancel</a>
                    </Button>
                    <Button onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving…' : toolKind === 'builder' ? 'Save & Export Class' : 'Save Webhook'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
