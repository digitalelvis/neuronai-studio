import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ProviderModelFields from './ProviderModelFields';
import StructuredOutputFields from './shared/StructuredOutputFields';
import StreamToggleField from './shared/StreamToggleField';
import RagFields from './shared/RagFields';
import { Checkbox } from '@/components/ui/checkbox';

export default function NodeConfigForm({
    node,
    agents,
    tools,
    mcpServers,
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    defaultProvider = '',
    defaultModel = '',
    readOnly,
    onUpdate,
    onRemove,
}) {
    if (!node) {
        return <p className="text-sm text-muted-foreground">Select a node to configure it.</p>;
    }

    const data = node.data || {};
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    const updateParametersJson = (json) => {
        try {
            const parameters = JSON.parse(json || '{}');
            onUpdate?.({ ...data, parameters_json: json, parameters });
        } catch {
            onUpdate?.({ ...data, parameters_json: json });
        }
    };

    const canRemove = !readOnly && !['start', 'stop'].includes(node.type);

    return (
        <div className="space-y-4">
            <div>
                <Label className="text-xs text-muted-foreground">Type</Label>
                <p className="text-sm font-medium capitalize">{node.type}</p>
            </div>

            {node.type === 'agent' && (
                <>
                    <div className="space-y-2">
                        <Label>Agent</Label>
                        <Select
                            value={data.agent_id ? String(data.agent_id) : ''}
                            onValueChange={(value) => updateField('agent_id', value)}
                            disabled={readOnly}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select agent" />
                            </SelectTrigger>
                            <SelectContent>
                                {agents.map((agent) => (
                                    <SelectItem key={agent.id} value={String(agent.id)}>
                                        {agent.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Message override</Label>
                        <Input
                            value={data.message ?? ''}
                            onChange={(e) => updateField('message', e.target.value)}
                            placeholder="$input"
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'agent_response'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            State key where the agent response is stored.
                        </p>
                    </div>
                    <StructuredOutputFields
                        structured={Boolean(data.structured)}
                        outputClass={data.output_class ?? ''}
                        outputClasses={outputClasses}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                    <StreamToggleField
                        stream={Boolean(data.stream)}
                        structured={Boolean(data.structured)}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                    <div className="space-y-2">
                        <Label>Tool max runs (override)</Label>
                        <Input
                            type="number"
                            min={1}
                            value={data.tool_max_runs ?? ''}
                            onChange={(e) =>
                                updateField(
                                    'tool_max_runs',
                                    e.target.value === '' ? undefined : Number(e.target.value),
                                )
                            }
                            placeholder="Inherit from agent"
                            disabled={readOnly}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            checked={Boolean(data.parallel_tool_calls)}
                            onCheckedChange={(checked) => updateField('parallel_tool_calls', Boolean(checked))}
                            disabled={readOnly}
                            id={`parallel-tools-${node.id}`}
                        />
                        <Label htmlFor={`parallel-tools-${node.id}`}>Parallel tool calls (override)</Label>
                    </div>
                    <div className="space-y-2 border-t border-border pt-3">
                        <Label className="text-xs font-medium uppercase text-muted-foreground">Memory override</Label>
                        <Input
                            type="number"
                            min={1}
                            value={data.context_window ?? ''}
                            onChange={(e) =>
                                updateField(
                                    'context_window',
                                    e.target.value === '' ? undefined : Number(e.target.value),
                                )
                            }
                            placeholder="Context window (inherit)"
                            disabled={readOnly}
                        />
                        <Select
                            value={data.driver || '__inherit'}
                            onValueChange={(value) =>
                                updateField('driver', value === '__inherit' ? undefined : value)
                            }
                            disabled={readOnly}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Driver (inherit)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__inherit">Driver: inherit</SelectItem>
                                <SelectItem value="eloquent">Eloquent</SelectItem>
                                <SelectItem value="in_memory">In-memory</SelectItem>
                            </SelectContent>
                        </Select>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.summarization_enabled === true}
                                onCheckedChange={(checked) =>
                                    updateField('summarization_enabled', checked ? true : undefined)
                                }
                                disabled={readOnly}
                            />
                            Summarization override
                        </label>
                        <Input
                            type="number"
                            min={1}
                            value={data.budget_rag ?? ''}
                            onChange={(e) =>
                                updateField(
                                    'budget_rag',
                                    e.target.value === '' ? undefined : Number(e.target.value),
                                )
                            }
                            placeholder="RAG budget (inherit)"
                            disabled={readOnly}
                        />
                        <Input
                            type="number"
                            min={1}
                            value={data.budget_tool_results ?? ''}
                            onChange={(e) =>
                                updateField(
                                    'budget_tool_results',
                                    e.target.value === '' ? undefined : Number(e.target.value),
                                )
                            }
                            placeholder="Tool results budget (inherit)"
                            disabled={readOnly}
                        />
                        <Input
                            type="number"
                            min={1}
                            value={data.budget_state ?? ''}
                            onChange={(e) =>
                                updateField(
                                    'budget_state',
                                    e.target.value === '' ? undefined : Number(e.target.value),
                                )
                            }
                            placeholder="State fields budget (inherit)"
                            disabled={readOnly}
                        />
                    </div>
                </>
            )}

            {node.type === 'llm' && (
                <>
                    <ProviderModelFields
                        provider={data.provider}
                        model={data.model}
                        providers={providers}
                        providerModels={providerModels}
                        defaultProvider={defaultProvider}
                        defaultModel={defaultModel}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                    <div className="space-y-2">
                        <Label>Prompt</Label>
                        <Textarea
                            rows={4}
                            value={data.prompt ?? ''}
                            onChange={(e) => updateField('prompt', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'llm_response'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            State key where the LLM response is stored.
                        </p>
                    </div>
                    <StructuredOutputFields
                        structured={Boolean(data.structured)}
                        outputClass={data.output_class ?? ''}
                        outputClasses={outputClasses}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                    <StreamToggleField
                        stream={Boolean(data.stream)}
                        structured={Boolean(data.structured)}
                        readOnly={readOnly}
                        onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                    />
                </>
            )}

            {node.type === 'human' && (
                <>
                    <div className="space-y-2">
                        <Label>Prompt</Label>
                        <Textarea
                            rows={3}
                            value={data.prompt ?? ''}
                            onChange={(e) => updateField('prompt', e.target.value)}
                            placeholder="Ask the user for input…"
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'human_response'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                </>
            )}

            {node.type === 'set_state' && (
                <>
                    <div className="space-y-2">
                        <Label>Key</Label>
                        <Input value={data.key ?? ''} onChange={(e) => updateField('key', e.target.value)} disabled={readOnly} />
                    </div>
                    <div className="space-y-2">
                        <Label>Value</Label>
                        <Input value={data.value ?? ''} onChange={(e) => updateField('value', e.target.value)} disabled={readOnly} />
                    </div>
                </>
            )}

            {node.type === 'condition' && (
                <>
                    <div className="space-y-2">
                        <Label>State Key</Label>
                        <Input
                            value={data.state_key ?? 'input'}
                            onChange={(e) => updateField('state_key', e.target.value)}
                            disabled={readOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            Key in workflow state. Use dot notation for nested values (e.g. lead.tier).
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Operator</Label>
                        <Select value={data.operator ?? 'not_empty'} onValueChange={(value) => updateField('operator', value)} disabled={readOnly}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="not_empty">is not empty</SelectItem>
                                <SelectItem value="empty">is empty</SelectItem>
                                <SelectItem value="equals">equals</SelectItem>
                                <SelectItem value="not_equals">does not equal</SelectItem>
                                <SelectItem value="contains">contains</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    {['equals', 'not_equals', 'contains'].includes(data.operator) && (
                        <div className="space-y-2">
                            <Label>Value</Label>
                            <Input value={data.value ?? ''} onChange={(e) => updateField('value', e.target.value)} disabled={readOnly} />
                        </div>
                    )}
                </>
            )}

            {node.type === 'loop' && (
                <>
                    <div className="space-y-2">
                        <Label>Max Steps</Label>
                        <Input
                            type="number"
                            min={1}
                            value={data.max_steps ?? 10}
                            onChange={(e) => updateField('max_steps', Number(e.target.value))}
                            disabled={readOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            Maximum iterations before the loop exits with an error.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Exit Condition — State Key</Label>
                        <Input
                            value={data.state_key ?? 'input'}
                            onChange={(e) => updateField('state_key', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Exit Condition — Operator</Label>
                        <Select value={data.operator ?? 'not_empty'} onValueChange={(value) => updateField('operator', value)} disabled={readOnly}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="not_empty">is not empty</SelectItem>
                                <SelectItem value="empty">is empty</SelectItem>
                                <SelectItem value="equals">equals</SelectItem>
                                <SelectItem value="not_equals">does not equal</SelectItem>
                                <SelectItem value="contains">contains</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    {['equals', 'not_equals', 'contains'].includes(data.operator) && (
                        <div className="space-y-2">
                            <Label>Exit Condition — Value</Label>
                            <Input value={data.value ?? ''} onChange={(e) => updateField('value', e.target.value)} disabled={readOnly} />
                        </div>
                    )}
                </>
            )}

            {node.type === 'fork' && (
                <ForkBranchEditor data={data} readOnly={readOnly} onUpdate={onUpdate} />
            )}

            {node.type === 'join' && (
                <>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'parallel_results'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            State key that receives the merged branch results, keyed by branch id
                            (e.g. {'{ branch_a: …, branch_b: … }'}).
                        </p>
                    </div>
                </>
            )}

            {node.type === 'tool' && (
                <>
                    <div className="space-y-2">
                        <Label>Tool</Label>
                        <Select value={data.tool_ref ?? ''} onValueChange={(value) => updateField('tool_ref', value)} disabled={readOnly}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select tool" />
                            </SelectTrigger>
                            <SelectContent>
                                {tools.map((tool) => (
                                    <SelectItem key={tool.ref} value={tool.ref}>
                                        {tool.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'tool_result'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Parameters JSON</Label>
                        <Textarea
                            rows={3}
                            value={data.parameters_json ?? (data.parameters ? JSON.stringify(data.parameters, null, 2) : '')}
                            onChange={(e) => updateParametersJson(e.target.value)}
                            placeholder='{"query": "$input"}'
                            disabled={readOnly}
                            className="font-mono text-xs"
                        />
                    </div>
                </>
            )}

            {node.type === 'mcp' && (
                <>
                    <div className="space-y-2">
                        <Label>MCP Server</Label>
                        <Select value={data.mcp_server ?? ''} onValueChange={(value) => updateField('mcp_server', value)} disabled={readOnly}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select server" />
                            </SelectTrigger>
                            <SelectContent>
                                {mcpServers.map((server) => (
                                    <SelectItem key={server.slug} value={server.slug}>
                                        {server.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Tool Name</Label>
                        <Input value={data.tool_name ?? ''} onChange={(e) => updateField('tool_name', e.target.value)} disabled={readOnly} />
                    </div>
                    <div className="space-y-2">
                        <Label>Output Key</Label>
                        <Input
                            value={data.output_key ?? 'mcp_result'}
                            onChange={(e) => updateField('output_key', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Parameters JSON</Label>
                        <Textarea
                            rows={3}
                            value={data.parameters_json ?? (data.parameters ? JSON.stringify(data.parameters, null, 2) : '')}
                            onChange={(e) => updateParametersJson(e.target.value)}
                            placeholder='{"query": "$input"}'
                            disabled={readOnly}
                            className="font-mono text-xs"
                        />
                    </div>
                </>
            )}

            {node.type === 'rag' && (
                <RagFields
                    data={data}
                    knowledgeBases={knowledgeBases}
                    ragSearchUrlTemplate={ragSearchUrlTemplate}
                    readOnly={readOnly}
                    onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                />
            )}

            {canRemove && (
                <Button variant="destructive" size="sm" onClick={onRemove}>
                    Remove Node
                </Button>
            )}
        </div>
    );
}

function normalizeBranches(branches) {
    if (!Array.isArray(branches)) {
        return [];
    }

    return branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

function ForkBranchEditor({ data, readOnly, onUpdate }) {
    const branches = normalizeBranches(data.branches);

    const commit = (next) => {
        onUpdate?.({ ...data, branches: next });
    };

    const addBranch = () => {
        const next = [...branches, `branch_${branches.length + 1}`];
        commit(next);
    };

    const renameBranch = (index, value) => {
        const next = branches.map((id, i) => (i === index ? value : id));
        commit(next);
    };

    const removeBranch = (index) => {
        commit(branches.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-3">
            <div>
                <Label>Branches</Label>
                <p className="text-xs text-muted-foreground">
                    Each branch adds a named output handle. Draw an edge from the handle to the
                    branch subgraph, then converge every branch back into a join node.
                </p>
            </div>

            {branches.length === 0 && (
                <p className="text-xs text-muted-foreground">No branches yet.</p>
            )}

            {branches.map((branchId, index) => (
                <div key={index} className="flex items-center gap-2">
                    <Input
                        value={branchId}
                        onChange={(e) => renameBranch(index, e.target.value)}
                        disabled={readOnly}
                        className="font-mono text-xs"
                    />
                    {!readOnly && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => removeBranch(index)}
                            title="Remove branch"
                        >
                            ✕
                        </Button>
                    )}
                </div>
            ))}

            {!readOnly && (
                <Button variant="outline" size="sm" onClick={addBranch}>
                    Add Branch
                </Button>
            )}
        </div>
    );
}
