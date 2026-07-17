import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { collectLivewireErrors, formatLivewireErrorSummary } from '@/lib/livewireErrors';
import ConnectPanel from '@/components/ConnectPanel';

const categoryLabels = {
    builtin: 'Built-in Toolkits',
    app: 'App Classes',
    studio: 'Studio Tools',
    mcp: 'MCP Servers',
};

export default function AgentForm({ config }) {
    const initial = config.initial ?? {};
    const [name, setName] = useState(initial.name ?? '');
    const [description, setDescription] = useState(initial.description ?? '');
    const [provider, setProvider] = useState(initial.provider ?? config.defaultProvider ?? '');
    const [model, setModel] = useState(initial.model ?? '');
    const [instructions, setInstructions] = useState(initial.instructions ?? '');
    const [selectedToolRefs, setSelectedToolRefs] = useState(initial.selectedToolRefs ?? []);
    const [toolAdvanced, setToolAdvanced] = useState(initial.toolAdvanced ?? {});
    const [selectedMcpSlugs, setSelectedMcpSlugs] = useState(initial.selectedMcpSlugs ?? []);
    const [mcpAdvanced, setMcpAdvanced] = useState(initial.mcpAdvanced ?? {});
    const [toolMaxRuns, setToolMaxRuns] = useState(
        initial.tool_max_runs === null || initial.tool_max_runs === undefined ? '' : String(initial.tool_max_runs),
    );
    const [parallelToolCalls, setParallelToolCalls] = useState(Boolean(initial.parallel_tool_calls));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const models = config.providerModels?.[provider] ?? config.models ?? [];

    const toolsByCategory = useMemo(() => {
        const grouped = {};
        (config.toolList ?? []).forEach((tool) => {
            const cat = tool.category || 'other';
            if (!grouped[cat]) grouped[cat] = [];
            grouped[cat].push(tool);
        });
        return grouped;
    }, [config.toolList]);

    const toggleTool = (ref) => {
        setSelectedToolRefs((current) =>
            current.includes(ref) ? current.filter((item) => item !== ref) : [...current, ref],
        );
    };

    const toggleMcp = (slug) => {
        setSelectedMcpSlugs((current) =>
            current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug],
        );
    };

    const updateToolAdvanced = (ref, field, value) => {
        setToolAdvanced((current) => ({
            ...current,
            [ref]: { ...(current[ref] ?? { only: '', exclude: '' }), [field]: value },
        }));
    };

    const updateMcpAdvanced = (slug, field, value) => {
        setMcpAdvanced((current) => ({
            ...current,
            [slug]: { ...(current[slug] ?? { only: '', exclude: '' }), [field]: value },
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setError('');

        try {
            const component = window.Livewire?.find(config.wireId);
            if (!component) {
                throw new Error('Livewire component not available.');
            }

            await component.call('saveFromReact', {
                name,
                description,
                provider,
                model,
                instructions,
                selectedToolRefs,
                toolAdvanced,
                selectedMcpSlugs,
                mcpAdvanced,
                tool_max_runs: toolMaxRuns === '' ? null : Number(toolMaxRuns),
                parallel_tool_calls: parallelToolCalls,
            });

            const validationErrors = collectLivewireErrors(config.wireId);
            if (Object.keys(validationErrors).length > 0) {
                setError(formatLivewireErrorSummary(validationErrors) || 'Please fix the validation errors.');
                return;
            }
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : 'Save failed.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex h-full min-h-0 flex-col bg-background">
            <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                <ResizablePanel defaultSize={55} minSize={40}>
                    <ScrollArea className="h-full p-4">
                        <div className="mx-auto max-w-2xl space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Agent details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Name</Label>
                                        <Input value={name} onChange={(e) => setName(e.target.value)} required />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Description</Label>
                                        <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Provider</Label>
                                            <Select
                                                value={provider}
                                                onValueChange={(value) => {
                                                    setProvider(value);
                                                    const nextModels = config.providerModels?.[value] ?? [];
                                                    if (nextModels.length) setModel(nextModels[0]);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(config.providers ?? {}).map(([key, label]) => (
                                                        <SelectItem key={key} value={key}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Model</Label>
                                            <Select value={model} onValueChange={setModel}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {models.map((m) => (
                                                        <SelectItem key={m} value={m}>
                                                            {m}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Instructions (System Prompt)</Label>
                                        <Textarea
                                            value={instructions}
                                            onChange={(e) => setInstructions(e.target.value)}
                                            rows={10}
                                            placeholder="You are a helpful assistant..."
                                            className="font-mono text-sm"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label>Tool max runs</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={toolMaxRuns}
                                                onChange={(e) => setToolMaxRuns(e.target.value)}
                                                placeholder="10 (Neuron default)"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Max tool rounds per node visit. Leave empty for Neuron default.
                                            </p>
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={parallelToolCalls}
                                                    onCheckedChange={(checked) => setParallelToolCalls(Boolean(checked))}
                                                />
                                                Parallel tool calls
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Run multiple tool calls in the same round concurrently when supported.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </ScrollArea>
                </ResizablePanel>
                <ResizableHandle withHandle />
                <ResizablePanel defaultSize={45} minSize={30}>
                    <div className="flex h-full flex-col p-4">
                        <Tabs defaultValue="tools" className="flex h-full flex-col">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="tools">Tools</TabsTrigger>
                                <TabsTrigger value="mcp">MCP Servers</TabsTrigger>
                                <TabsTrigger value="connect">Connect</TabsTrigger>
                            </TabsList>
                            <TabsContent value="tools" className="mt-3 flex-1 overflow-hidden">
                                <ScrollArea className="h-full pr-2">
                                    {Object.keys(toolsByCategory).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">No tools registered.</p>
                                    ) : (
                                        Object.entries(toolsByCategory).map(([category, tools]) => (
                                            <div key={category} className="mb-4">
                                                <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">
                                                    {categoryLabels[category] ?? category}
                                                </p>
                                                <div className="space-y-2">
                                                    {tools.map((tool) => (
                                                        <div key={tool.ref} className="rounded-md border border-border p-3">
                                                            <label className="flex cursor-pointer items-start gap-3">
                                                                <Checkbox
                                                                    checked={selectedToolRefs.includes(tool.ref)}
                                                                    onCheckedChange={() => toggleTool(tool.ref)}
                                                                    className="mt-0.5"
                                                                />
                                                                <span className="flex-1">
                                                                    <span className="font-medium">{tool.label}</span>
                                                                    {tool.description && (
                                                                        <span className="block text-xs text-muted-foreground">{tool.description}</span>
                                                                    )}
                                                                    <Badge variant="outline" className="mt-1 text-[10px]">
                                                                        {tool.ref}
                                                                    </Badge>
                                                                </span>
                                                            </label>
                                                            {selectedToolRefs.includes(tool.ref) && ['toolkit', 'mcp'].includes(tool.type) && (
                                                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                                                    <Input
                                                                        placeholder="Only (comma-separated)"
                                                                        value={toolAdvanced[tool.ref]?.only ?? ''}
                                                                        onChange={(e) => updateToolAdvanced(tool.ref, 'only', e.target.value)}
                                                                    />
                                                                    <Input
                                                                        placeholder="Exclude (comma-separated)"
                                                                        value={toolAdvanced[tool.ref]?.exclude ?? ''}
                                                                        onChange={(e) => updateToolAdvanced(tool.ref, 'exclude', e.target.value)}
                                                                    />
                                                                </div>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </ScrollArea>
                            </TabsContent>
                            <TabsContent value="mcp" className="mt-3 flex-1 overflow-hidden">
                                <ScrollArea className="h-full pr-2">
                                    {Object.keys(config.mcpServers ?? {}).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">No MCP servers available.</p>
                                    ) : (
                                        Object.entries(config.mcpServers).map(([slug, label]) => (
                                            <div key={slug} className="mb-2 rounded-md border border-border p-3">
                                                <label className="flex cursor-pointer items-start gap-3">
                                                    <Checkbox
                                                        checked={selectedMcpSlugs.includes(slug)}
                                                        onCheckedChange={() => toggleMcp(slug)}
                                                        className="mt-0.5"
                                                    />
                                                    <span>
                                                        <span className="font-medium">{label}</span>
                                                        <Badge variant="outline" className="ml-2 text-[10px]">
                                                            {slug}
                                                        </Badge>
                                                    </span>
                                                </label>
                                                {selectedMcpSlugs.includes(slug) && (
                                                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                                                        <Input
                                                            placeholder="Only (comma-separated)"
                                                            value={mcpAdvanced[slug]?.only ?? ''}
                                                            onChange={(e) => updateMcpAdvanced(slug, 'only', e.target.value)}
                                                        />
                                                        <Input
                                                            placeholder="Exclude (comma-separated)"
                                                            value={mcpAdvanced[slug]?.exclude ?? ''}
                                                            onChange={(e) => updateMcpAdvanced(slug, 'exclude', e.target.value)}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </ScrollArea>
                            </TabsContent>
                            <TabsContent value="connect" className="mt-3 flex-1 overflow-hidden">
                                <ConnectPanel
                                    protocols={config.enabledProtocols ?? ['vercel', 'agui']}
                                    streamUrls={config.streamUrls ?? {}}
                                    type="agent"
                                />
                            </TabsContent>
                        </Tabs>
                    </div>
                </ResizablePanel>
            </ResizablePanelGroup>

            <div className="flex shrink-0 items-center justify-between border-t border-border px-4 py-3">
                {error && <span className="text-sm text-destructive">{error}</span>}
                <div className="ml-auto flex gap-2">
                    <Button variant="outline" asChild>
                        <a href={config.cancelUrl}>Cancel</a>
                    </Button>
                    <Button onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving…' : 'Save Agent'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
