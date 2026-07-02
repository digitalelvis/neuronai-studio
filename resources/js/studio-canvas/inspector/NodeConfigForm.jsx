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

export default function NodeConfigForm({
    node,
    agents,
    tools,
    mcpServers,
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
                            Key in workflow state. Defaults to input.
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

            {canRemove && (
                <Button variant="destructive" size="sm" onClick={onRemove}>
                    Remove Node
                </Button>
            )}
        </div>
    );
}
