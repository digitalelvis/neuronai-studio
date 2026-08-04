import { useMemo, useState } from 'react';
import { Check, ChevronDown, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { collectLivewireErrors, formatLivewireErrorSummary } from '@/lib/livewireErrors';
import ConnectPanel from '@/components/ConnectPanel';
import { t } from '@/lib/i18n';
import VariableInput from './VariableInput';

const DESCRIPTION_MAX = 120;

const categoryLabels = {
    builtin: 'form.tools_builtin',
    app: 'form.tools_app',
    studio: 'form.tools_studio',
    mcp: 'form.tools_mcp',
};

function truncateText(text, max = DESCRIPTION_MAX) {
    if (!text) return '';
    if (text.length <= max) return text;
    return `${text.slice(0, max)}…`;
}

function matchesQuery(haystacks, query) {
    if (!query) return true;
    const q = query.toLowerCase();
    return haystacks.some((value) => String(value ?? '').toLowerCase().includes(q));
}

function groupByCategory(tools) {
    const grouped = {};
    tools.forEach((tool) => {
        const cat = tool.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(tool);
    });
    return grouped;
}

function CopySlugButton({ value, copyKey, copiedKey, onCopy }) {
    if (!value) return null;

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-6 w-6 shrink-0"
            aria-label={t('form.copy_slug')}
            onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onCopy(value, copyKey);
            }}
        >
            {copiedKey === copyKey ? (
                <Check className="h-3 w-3 text-green-500" />
            ) : (
                <Copy className="h-3 w-3" />
            )}
        </Button>
    );
}

export default function AgentForm({ config }) {
    const initial = config.initial ?? {};
    const [name, setName] = useState(initial.name ?? '');
    const [description, setDescription] = useState(initial.description ?? '');
    const [provider, setProvider] = useState(initial.provider ?? config.defaultProvider ?? '');
    const [model, setModel] = useState(initial.model ?? '');
    const [instructions, setInstructions] = useState(initial.instructions ?? '');
    const [apiKey, setApiKey] = useState(initial.api_key ?? '');
    const [selectedToolRefs, setSelectedToolRefs] = useState(initial.selectedToolRefs ?? []);
    const [toolAdvanced, setToolAdvanced] = useState(initial.toolAdvanced ?? {});
    const [selectedMcpSlugs, setSelectedMcpSlugs] = useState(initial.selectedMcpSlugs ?? []);
    const [mcpAdvanced, setMcpAdvanced] = useState(initial.mcpAdvanced ?? {});
    const [toolMaxRuns, setToolMaxRuns] = useState(
        initial.tool_max_runs === null || initial.tool_max_runs === undefined ? '' : String(initial.tool_max_runs),
    );
    const [parallelToolCalls, setParallelToolCalls] = useState(Boolean(initial.parallel_tool_calls));
    const [memoryContextWindow, setMemoryContextWindow] = useState(
        initial.memory_context_window === null || initial.memory_context_window === undefined
            ? ''
            : String(initial.memory_context_window),
    );
    const [memoryDriver, setMemoryDriver] = useState(initial.memory_driver ?? '');
    const [memorySummarizationEnabled, setMemorySummarizationEnabled] = useState(
        initial.memory_summarization_enabled === null || initial.memory_summarization_enabled === undefined
            ? null
            : Boolean(initial.memory_summarization_enabled),
    );
    const [memorySummarizationThreshold, setMemorySummarizationThreshold] = useState(
        initial.memory_summarization_threshold === null || initial.memory_summarization_threshold === undefined
            ? ''
            : String(initial.memory_summarization_threshold),
    );
    const [memoryBudgetRag, setMemoryBudgetRag] = useState(
        initial.memory_budget_rag === null || initial.memory_budget_rag === undefined
            ? ''
            : String(initial.memory_budget_rag),
    );
    const [memoryBudgetToolResults, setMemoryBudgetToolResults] = useState(
        initial.memory_budget_tool_results === null || initial.memory_budget_tool_results === undefined
            ? ''
            : String(initial.memory_budget_tool_results),
    );
    const [memoryBudgetState, setMemoryBudgetState] = useState(
        initial.memory_budget_state === null || initial.memory_budget_state === undefined
            ? ''
            : String(initial.memory_budget_state),
    );
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [toolsSearch, setToolsSearch] = useState('');
    const [toolkitsSearch, setToolkitsSearch] = useState('');
    const [mcpSearch, setMcpSearch] = useState('');
    const [copiedKey, setCopiedKey] = useState(null);
    const [expandedKits, setExpandedKits] = useState({});

    const models = config.providerModels?.[provider] ?? config.models ?? [];

    const { tools, toolkits } = useMemo(() => {
        const all = config.toolList ?? [];
        return {
            tools: all.filter((tool) => tool.type !== 'toolkit'),
            toolkits: all.filter((tool) => tool.type === 'toolkit'),
        };
    }, [config.toolList]);

    const filteredToolsByCategory = useMemo(() => {
        const filtered = tools.filter((tool) =>
            matchesQuery([tool.label, tool.ref, tool.slug, tool.description], toolsSearch),
        );
        return groupByCategory(filtered);
    }, [tools, toolsSearch]);

    const filteredToolkits = useMemo(() => {
        return toolkits.filter((kit) => {
            const childNames = (kit.tools ?? []).flatMap((child) => [child.name, child.description]);
            return matchesQuery([kit.label, kit.ref, kit.description, ...childNames], toolkitsSearch);
        });
    }, [toolkits, toolkitsSearch]);

    const filteredMcpServers = useMemo(() => {
        return Object.entries(config.mcpServers ?? {}).filter(([slug, label]) =>
            matchesQuery([slug, label], mcpSearch),
        );
    }, [config.mcpServers, mcpSearch]);

    const handleCopy = (text, key) => {
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopiedKey(key);
        setTimeout(() => setCopiedKey(null), 2000);
    };

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
                api_key: apiKey,
                selectedToolRefs,
                toolAdvanced,
                selectedMcpSlugs,
                mcpAdvanced,
                tool_max_runs: toolMaxRuns === '' ? null : Number(toolMaxRuns),
                parallel_tool_calls: parallelToolCalls,
                memory_context_window: memoryContextWindow === '' ? null : Number(memoryContextWindow),
                memory_driver: memoryDriver === '' ? null : memoryDriver,
                memory_summarization_enabled: memorySummarizationEnabled,
                memory_summarization_threshold:
                    memorySummarizationThreshold === '' ? null : Number(memorySummarizationThreshold),
                memory_budget_rag: memoryBudgetRag === '' ? null : Number(memoryBudgetRag),
                memory_budget_tool_results: memoryBudgetToolResults === '' ? null : Number(memoryBudgetToolResults),
                memory_budget_state: memoryBudgetState === '' ? null : Number(memoryBudgetState),
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

    const renderToolCard = (tool) => {
        const truncatedDescription = truncateText(tool.description);

        return (
            <div key={tool.ref} className="rounded-md border border-border p-3">
                <label className="flex cursor-pointer items-start gap-3">
                    <Checkbox
                        checked={selectedToolRefs.includes(tool.ref)}
                        onCheckedChange={() => toggleTool(tool.ref)}
                        className="mt-0.5"
                    />
                    <span className="min-w-0 flex-1">
                        <span className="font-medium">{tool.label}</span>
                        {tool.slug && (
                            <span className="mt-1 flex items-center gap-1">
                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-foreground">
                                    {tool.slug}
                                </code>
                                <CopySlugButton
                                    value={tool.slug}
                                    copyKey={`tool-slug-${tool.ref}`}
                                    copiedKey={copiedKey}
                                    onCopy={handleCopy}
                                />
                            </span>
                        )}
                        {tool.description && (
                            <span
                                className="mt-1 block text-xs text-muted-foreground"
                                title={tool.description.length > DESCRIPTION_MAX ? tool.description : undefined}
                            >
                                {truncatedDescription}
                            </span>
                        )}
                        <Badge variant="outline" className="mt-1 text-[10px]">
                            {tool.ref}
                        </Badge>
                    </span>
                </label>
                {selectedToolRefs.includes(tool.ref) && tool.type === 'mcp' && (
                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                        <Input
                            placeholder={t('form.only_placeholder')}
                            value={toolAdvanced[tool.ref]?.only ?? ''}
                            onChange={(e) => updateToolAdvanced(tool.ref, 'only', e.target.value)}
                        />
                        <Input
                            placeholder={t('form.exclude_placeholder')}
                            value={toolAdvanced[tool.ref]?.exclude ?? ''}
                            onChange={(e) => updateToolAdvanced(tool.ref, 'exclude', e.target.value)}
                        />
                    </div>
                )}
            </div>
        );
    };

    const renderToolkitCard = (kit) => {
        const truncatedDescription = truncateText(kit.description);
        const childTools = kit.tools ?? [];
        const kitExpanded = Boolean(expandedKits[kit.ref]);

        return (
            <div key={kit.ref} className="rounded-md border border-border p-3">
                <label className="flex cursor-pointer items-start gap-3">
                    <Checkbox
                        checked={selectedToolRefs.includes(kit.ref)}
                        onCheckedChange={() => toggleTool(kit.ref)}
                        className="mt-0.5"
                    />
                    <span className="min-w-0 flex-1">
                        <span className="font-medium">{kit.label}</span>
                        {kit.description && (
                            <span
                                className="mt-1 block text-xs text-muted-foreground"
                                title={kit.description.length > DESCRIPTION_MAX ? kit.description : undefined}
                            >
                                {truncatedDescription}
                            </span>
                        )}
                        <Badge variant="outline" className="mt-1 text-[10px]">
                            {kit.ref}
                        </Badge>
                    </span>
                </label>

                {childTools.length > 0 && (
                    <Collapsible
                        open={kitExpanded}
                        onOpenChange={(open) =>
                            setExpandedKits((current) => ({ ...current, [kit.ref]: open }))
                        }
                        className="mt-3 border-t border-border pt-2"
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md px-1 py-1 text-left text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:bg-muted/40">
                            <span>
                                {t('form.kit_tools')}
                                <span className="ml-1 font-normal normal-case text-muted-foreground/80">
                                    ({childTools.length})
                                </span>
                            </span>
                            <ChevronDown
                                className={`h-3.5 w-3.5 shrink-0 transition-transform ${kitExpanded ? '' : '-rotate-90'}`}
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-1.5 pt-2">
                            <ul className="space-y-1.5">
                                {childTools.map((child) => (
                                    <li key={`${kit.ref}-${child.name}`} className="rounded bg-muted/40 px-2 py-1.5">
                                        <div className="flex items-center gap-1">
                                            <code className="font-mono text-[11px] text-foreground">{child.name}</code>
                                            <CopySlugButton
                                                value={child.name}
                                                copyKey={`kit-tool-${kit.ref}-${child.name}`}
                                                copiedKey={copiedKey}
                                                onCopy={handleCopy}
                                            />
                                        </div>
                                        {child.description && (
                                            <p
                                                className="mt-0.5 text-[11px] text-muted-foreground"
                                                title={
                                                    child.description.length > DESCRIPTION_MAX
                                                        ? child.description
                                                        : undefined
                                                }
                                            >
                                                {truncateText(child.description)}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CollapsibleContent>
                    </Collapsible>
                )}

                {selectedToolRefs.includes(kit.ref) && (
                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                        <Input
                            placeholder={t('form.only_placeholder')}
                            value={toolAdvanced[kit.ref]?.only ?? ''}
                            onChange={(e) => updateToolAdvanced(kit.ref, 'only', e.target.value)}
                        />
                        <Input
                            placeholder={t('form.exclude_placeholder')}
                            value={toolAdvanced[kit.ref]?.exclude ?? ''}
                            onChange={(e) => updateToolAdvanced(kit.ref, 'exclude', e.target.value)}
                        />
                    </div>
                )}
            </div>
        );
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
                                        <ExpandableTextField
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            rows={2}
                                            label="Edit text content"
                                        />
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
                                        <Label>API Key (optional override)</Label>
                                        <VariableInput
                                            value={apiKey}
                                            onChange={setApiKey}
                                            variables={config.variables ?? []}
                                            sensitive
                                            placeholder=""
                                            hint="Bind a Credential variable (var:NAME) or leave empty for install-time config."
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Instructions (System Prompt)</Label>
                                        <ExpandableTextField
                                            value={instructions}
                                            onChange={(e) => setInstructions(e.target.value)}
                                            rows={10}
                                            placeholder={t('form.instructions_placeholder')}
                                            className="font-mono text-sm"
                                            label="Edit text content"
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
                                    <div className="space-y-3 border-t border-border pt-4">
                                        <p className="text-sm font-medium">Memory</p>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Context window (tokens)</Label>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={memoryContextWindow}
                                                    onChange={(e) => setMemoryContextWindow(e.target.value)}
                                                    placeholder="Global default"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>History driver</Label>
                                                <Select
                                                    value={memoryDriver || '__inherit'}
                                                    onValueChange={(value) =>
                                                        setMemoryDriver(value === '__inherit' ? '' : value)
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Inherit" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="__inherit">Inherit (by thread)</SelectItem>
                                                        <SelectItem value="eloquent">Eloquent (persist)</SelectItem>
                                                        <SelectItem value="in_memory">In-memory</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={memorySummarizationEnabled === true}
                                                        onCheckedChange={(checked) =>
                                                            setMemorySummarizationEnabled(checked ? true : null)
                                                        }
                                                    />
                                                    Summarization (compaction)
                                                </Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Replace trimmed history with a persisted summary. Leave off to
                                                    inherit / disable.
                                                </p>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Summarization threshold</Label>
                                                <Input
                                                    type="number"
                                                    min={0.01}
                                                    max={1}
                                                    step={0.05}
                                                    value={memorySummarizationThreshold}
                                                    onChange={(e) => setMemorySummarizationThreshold(e.target.value)}
                                                    placeholder="0.8 (optional)"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2 border-t border-border pt-3">
                                            <p className="text-sm font-medium">Prompt assembly budgets</p>
                                            <p className="text-xs text-muted-foreground">
                                                Optional token caps for RAG chunks, tool results, and interpolated
                                                state fields. Empty = disabled (pass-through).
                                            </p>
                                            <div className="grid gap-4 md:grid-cols-3">
                                                <div className="space-y-2">
                                                    <Label>RAG budget</Label>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        value={memoryBudgetRag}
                                                        onChange={(e) => setMemoryBudgetRag(e.target.value)}
                                                        placeholder="Off"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Tool results budget</Label>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        value={memoryBudgetToolResults}
                                                        onChange={(e) => setMemoryBudgetToolResults(e.target.value)}
                                                        placeholder="Off"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>State fields budget</Label>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        value={memoryBudgetState}
                                                        onChange={(e) => setMemoryBudgetState(e.target.value)}
                                                        placeholder="Off"
                                                    />
                                                </div>
                                            </div>
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
                            <TabsList className="grid w-full grid-cols-4">
                                <TabsTrigger value="tools">{t('form.tab_tools')}</TabsTrigger>
                                <TabsTrigger value="toolkits">{t('form.tab_toolkits')}</TabsTrigger>
                                <TabsTrigger value="mcp">{t('form.tab_mcp')}</TabsTrigger>
                                <TabsTrigger value="connect">{t('form.tab_connect')}</TabsTrigger>
                            </TabsList>

                            <TabsContent value="tools" className="mt-3 flex flex-1 flex-col overflow-hidden">
                                <Input
                                    value={toolsSearch}
                                    onChange={(e) => setToolsSearch(e.target.value)}
                                    placeholder={t('form.search_placeholder')}
                                    className="mb-2 shrink-0"
                                />
                                <ScrollArea className="h-full flex-1 pr-2">
                                    {tools.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.tools_empty')}</p>
                                    ) : Object.keys(filteredToolsByCategory).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.search_no_matches')}</p>
                                    ) : (
                                        Object.entries(filteredToolsByCategory).map(([category, categoryTools]) => (
                                            <div key={category} className="mb-4">
                                                <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">
                                                    {t(categoryLabels[category] ?? category)}
                                                </p>
                                                <div className="space-y-2">
                                                    {categoryTools.map((tool) => renderToolCard(tool))}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </ScrollArea>
                            </TabsContent>

                            <TabsContent value="toolkits" className="mt-3 flex flex-1 flex-col overflow-hidden">
                                <Input
                                    value={toolkitsSearch}
                                    onChange={(e) => setToolkitsSearch(e.target.value)}
                                    placeholder={t('form.search_placeholder')}
                                    className="mb-2 shrink-0"
                                />
                                <ScrollArea className="h-full flex-1 pr-2">
                                    {toolkits.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.toolkits_empty')}</p>
                                    ) : filteredToolkits.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.search_no_matches')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {filteredToolkits.map((kit) => renderToolkitCard(kit))}
                                        </div>
                                    )}
                                </ScrollArea>
                            </TabsContent>

                            <TabsContent value="mcp" className="mt-3 flex flex-1 flex-col overflow-hidden">
                                <Input
                                    value={mcpSearch}
                                    onChange={(e) => setMcpSearch(e.target.value)}
                                    placeholder={t('form.search_placeholder')}
                                    className="mb-2 shrink-0"
                                />
                                <ScrollArea className="h-full flex-1 pr-2">
                                    {Object.keys(config.mcpServers ?? {}).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.mcp_empty')}</p>
                                    ) : filteredMcpServers.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('form.search_no_matches')}</p>
                                    ) : (
                                        filteredMcpServers.map(([slug, label]) => (
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
                                                            placeholder={t('form.only_placeholder')}
                                                            value={mcpAdvanced[slug]?.only ?? ''}
                                                            onChange={(e) => updateMcpAdvanced(slug, 'only', e.target.value)}
                                                        />
                                                        <Input
                                                            placeholder={t('form.exclude_placeholder')}
                                                            value={mcpAdvanced[slug]?.exclude ?? ''}
                                                            onChange={(e) =>
                                                                updateMcpAdvanced(slug, 'exclude', e.target.value)
                                                            }
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
