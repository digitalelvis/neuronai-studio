import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { collectLivewireErrors, fieldError, formatLivewireErrorSummary } from '@/lib/livewireErrors';

export default function ToolBuilder({ config }) {
    const initial = config.initial ?? {};
    const knowledgeBases = config.knowledgeBases ?? [];
    const [toolKind, setToolKind] = useState(initial.toolKind ?? 'builder');
    const [name, setName] = useState(initial.name ?? '');
    const [toolName, setToolName] = useState(initial.toolName ?? '');
    const [description, setDescription] = useState(initial.description ?? '');
    const [method, setMethod] = useState(initial.method ?? 'GET');
    const [url, setUrl] = useState(initial.url ?? '');
    const [headersJson, setHeadersJson] = useState(initial.headersJson ?? '{}');
    const [invokeBody, setInvokeBody] = useState(initial.invokeBody ?? '');
    const [inputSchema, setInputSchema] = useState(initial.inputSchema ?? []);
    const [knowledgeBaseId, setKnowledgeBaseId] = useState(
        initial.knowledgeBaseId != null ? String(initial.knowledgeBaseId) : '',
    );
    const [topK, setTopK] = useState(initial.topK != null ? String(initial.topK) : '');
    const [threshold, setThreshold] = useState(initial.threshold != null ? String(initial.threshold) : '');
    const [preview, setPreview] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

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
        setFieldErrors({});

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
                knowledgeBaseId: knowledgeBaseId !== '' ? Number(knowledgeBaseId) : null,
                topK: topK !== '' ? Number(topK) : null,
                threshold: threshold !== '' ? Number(threshold) : null,
            });

            const validationErrors = collectLivewireErrors(config.wireId);
            if (Object.keys(validationErrors).length > 0) {
                setFieldErrors(validationErrors);
                setError(formatLivewireErrorSummary(validationErrors) || 'Please fix the validation errors.');
                return;
            }
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : 'Save failed.');
        } finally {
            setSaving(false);
        }
    };

    const inputSchemaError = Object.entries(fieldErrors)
        .filter(([key]) => key.startsWith('inputSchema'))
        .flatMap(([, messages]) => messages)[0];

    const saveLabel =
        toolKind === 'builder'
            ? 'Save & Export Class'
            : toolKind === 'rag'
              ? 'Save RAG Tool'
              : 'Save Webhook';

    const showProperties = toolKind !== 'rag';
    const showPreviewPanel = toolKind === 'builder';
    const panelWidth = showPreviewPanel ? 55 : 100;

    return (
        <div className="flex h-full min-h-0 flex-col bg-background">
            <div className="shrink-0 border-b border-border px-4 py-3">
                <Tabs value={toolKind} onValueChange={setToolKind}>
                    <TabsList>
                        <TabsTrigger value="builder">PHP Class Builder</TabsTrigger>
                        <TabsTrigger value="webhook">Webhook</TabsTrigger>
                        <TabsTrigger value="rag">RAG - Knowledge Base</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                <ResizablePanel defaultSize={panelWidth} minSize={40}>
                    <ScrollArea className="h-full p-4">
                        <div className="mx-auto max-w-2xl space-y-4">
                            <div className="space-y-2">
                                <Label>Display Name</Label>
                                <Input value={name} onChange={(e) => setName(e.target.value)} required />
                                {fieldError(fieldErrors, 'name') && (
                                    <p className="text-xs text-destructive">{fieldError(fieldErrors, 'name')}</p>
                                )}
                            </div>
                            {(toolKind === 'builder' || toolKind === 'rag') && (
                                <div className="space-y-2">
                                    <Label>Tool Name (function identifier)</Label>
                                    <Input
                                        value={toolName}
                                        onChange={(e) => setToolName(e.target.value)}
                                        placeholder={toolKind === 'rag' ? 'search_knowledge_base' : 'check_server_test'}
                                    />
                                    {fieldError(fieldErrors, 'toolName') && (
                                        <p className="text-xs text-destructive">{fieldError(fieldErrors, 'toolName')}</p>
                                    )}
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label>Description</Label>
                                <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
                                {fieldError(fieldErrors, 'description') && (
                                    <p className="text-xs text-destructive">{fieldError(fieldErrors, 'description')}</p>
                                )}
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
                                            {fieldError(fieldErrors, 'url') && (
                                                <p className="text-xs text-destructive">{fieldError(fieldErrors, 'url')}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Headers (JSON)</Label>
                                        <Textarea value={headersJson} onChange={(e) => setHeadersJson(e.target.value)} rows={4} className="font-mono text-xs" />
                                        {fieldError(fieldErrors, 'headersJson') && (
                                            <p className="text-xs text-destructive">{fieldError(fieldErrors, 'headersJson')}</p>
                                        )}
                                    </div>
                                </>
                            )}

                            {toolKind === 'rag' && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Knowledge Base</Label>
                                        <Select
                                            value={knowledgeBaseId}
                                            onValueChange={setKnowledgeBaseId}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a knowledge base" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {knowledgeBases.map((kb) => (
                                                    <SelectItem key={kb.id} value={String(kb.id)}>
                                                        {kb.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {knowledgeBases.length === 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                No knowledge bases yet. Create one under Knowledge Bases first.
                                            </p>
                                        )}
                                        {knowledgeBases.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                After saving, bind this tool on an agent so the model can search on demand.
                                            </p>
                                        )}
                                        {fieldError(fieldErrors, 'knowledgeBaseId') && (
                                            <p className="text-xs text-destructive">{fieldError(fieldErrors, 'knowledgeBaseId')}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Top K (optional)</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={100}
                                                value={topK}
                                                onChange={(e) => setTopK(e.target.value)}
                                                placeholder="Default from KB"
                                            />
                                            {fieldError(fieldErrors, 'topK') && (
                                                <p className="text-xs text-destructive">{fieldError(fieldErrors, 'topK')}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Threshold (optional)</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={1}
                                                step={0.01}
                                                value={threshold}
                                                onChange={(e) => setThreshold(e.target.value)}
                                                placeholder="Default from KB"
                                            />
                                            {fieldError(fieldErrors, 'threshold') && (
                                                <p className="text-xs text-destructive">{fieldError(fieldErrors, 'threshold')}</p>
                                            )}
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        The agent passes a <code className="rounded bg-muted px-1">query</code> argument.
                                        This tool searches the linked knowledge base and returns matching document excerpts.
                                    </p>
                                </>
                            )}

                            {showProperties && (
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                                        <CardTitle className="text-sm">Properties</CardTitle>
                                        <Button type="button" variant="outline" size="sm" onClick={addProperty}>
                                            Add Property
                                        </Button>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        {inputSchemaError && (
                                            <p className="text-xs text-destructive">{inputSchemaError}</p>
                                        )}
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
                            )}

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
                                    {fieldError(fieldErrors, 'invokeBody') && (
                                        <p className="text-xs text-destructive">{fieldError(fieldErrors, 'invokeBody')}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Write only the method body. Parameters are generated from properties above.</p>
                                </div>
                            )}
                        </div>
                    </ScrollArea>
                </ResizablePanel>

                {showPreviewPanel && (
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
                        {saving ? 'Saving…' : saveLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
