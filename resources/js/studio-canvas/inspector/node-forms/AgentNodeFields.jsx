import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ProviderModelFields from '../ProviderModelFields';
import StructuredOutputFields from '../shared/StructuredOutputFields';
import StreamToggleField from '../shared/StreamToggleField';
import { resolveAgentConfigMode, isToolModeEnabled, isNodeTypeToolable, defaultToolExposure } from '../nodeUtils';
import { StateVariableTextField } from '../shared/state-variables';
import ApiKeyField from './fields/ApiKeyField';
import OutputKeyField from './fields/OutputKeyField';
import ToolModeToggle from './fields/ToolModeToggle';

export default function AgentNodeFields({
    node,
    data,
    agents = [],
    providers = {},
    providerModels = {},
    variables = [],
    outputClasses = [],
    defaultProvider = '',
    defaultModel = '',
    typeMeta = {},
    readOnly = false,
    compact = false,
    showControls = true,
    showAdvanced = true,
    onUpdate,
}) {
    const toolable = isNodeTypeToolable(typeMeta);
    const toolMode = isToolModeEnabled(data);

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    const setToolMode = (enabled) => {
        if (!enabled) {
            onUpdate?.({ ...data, tool_mode: false });
            return;
        }

        onUpdate?.({
            ...data,
            tool_mode: true,
            tool_exposure: defaultToolExposure(data, typeMeta),
        });
    };

    const openActions = () => {
        window.dispatchEvent(
            new CustomEvent('canvas-tool-exposure-edit', {
                detail: { id: node.id, data, nodeType: node.type },
            }),
        );
    };

    return (
        <>
            {showControls && (
                <>
                    {toolable && (
                        <ToolModeToggle
                            nodeId={node.id}
                            toolMode={toolMode}
                            readOnly={readOnly}
                            onCheckedChange={setToolMode}
                        />
                    )}

                    <div className="grid grid-cols-2 gap-0.5 rounded-md border border-border p-0.5">
                        <Button
                            type="button"
                            size="sm"
                            variant={resolveAgentConfigMode(data) === 'existing' ? 'default' : 'ghost'}
                            className="h-7 px-2 text-[11px]"
                            disabled={readOnly}
                            onClick={() =>
                                onUpdate?.({
                                    ...data,
                                    config_mode: 'existing',
                                    provider: undefined,
                                    model: undefined,
                                    instructions: undefined,
                                    api_key: undefined,
                                })
                            }
                        >
                            Use existing
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={resolveAgentConfigMode(data) === 'inline' ? 'default' : 'ghost'}
                            className="h-7 px-2 text-[11px]"
                            disabled={readOnly}
                            onClick={() =>
                                onUpdate?.({
                                    ...data,
                                    config_mode: 'inline',
                                    agent_id: undefined,
                                    provider: data.provider || defaultProvider,
                                    model: data.model || defaultModel,
                                })
                            }
                        >
                            Configure on canvas
                        </Button>
                    </div>

                    {resolveAgentConfigMode(data) === 'existing' ? (
                        <>
                            <div className="space-y-2">
                                <Label>Agent</Label>
                                <Combobox
                                    options={agents.map((agent) => ({
                                        value: String(agent.id),
                                        label: agent.name,
                                    }))}
                                    value={data.agent_id ? String(data.agent_id) : ''}
                                    onValueChange={(value) => updateField('agent_id', value)}
                                    placeholder="Select agent"
                                    searchPlaceholder="Search agents…"
                                    disabled={readOnly}
                                />
                            </div>
                            <div className="space-y-1" data-ab-handle-anchor="tools">
                                <Label>Tools</Label>
                                <p className="ab-flow-agent-tools-hint">
                                    Connect Tool or MCP nodes to the cyan tools handle.
                                    {toolMode
                                        ? ' Connect the amber toolset handle to a supervisor tools pin.'
                                        : ''}
                                </p>
                            </div>
                            {!toolMode && (
                                <div className="space-y-2" data-ab-handle-anchor="input">
                                    <Label>Message override</Label>
                                    <StateVariableTextField
                                        value={data.message ?? ''}
                                        onChange={(e) => updateField('message', e.target.value)}
                                        currentNodeId={node.id}
                                        placeholder="{{input}}"
                                        disabled={readOnly}
                                        compact={compact}
                                        rows={compact ? 2 : 3}
                                        label="Edit message override"
                                    />
                                </div>
                            )}
                        </>
                    ) : (
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
                            <ApiKeyField
                                value={data.api_key}
                                onChange={(value) => updateField('api_key', value)}
                                variables={variables}
                                readOnly={readOnly}
                            />
                            <div className="space-y-2">
                                <Label>Agent Instructions</Label>
                                <StateVariableTextField
                                    rows={compact ? 3 : 5}
                                    value={data.instructions ?? ''}
                                    onChange={(e) => updateField('instructions', e.target.value)}
                                    currentNodeId={node.id}
                                    placeholder="You are a helpful assistant…"
                                    disabled={readOnly}
                                    compact={compact}
                                    label="Edit agent instructions"
                                />
                            </div>
                            <div className="space-y-1" data-ab-handle-anchor="tools">
                                <Label>Tools</Label>
                                <p className="ab-flow-agent-tools-hint">
                                    Connect Tool or MCP nodes to the cyan tools handle.
                                    {toolMode
                                        ? ' Connect the amber toolset handle to a supervisor tools pin.'
                                        : ''}
                                </p>
                            </div>
                            {!toolMode && (
                                <div className="space-y-2" data-ab-handle-anchor="input">
                                    <Label>Input</Label>
                                    <StateVariableTextField
                                        value={data.message ?? ''}
                                        onChange={(e) => updateField('message', e.target.value)}
                                        currentNodeId={node.id}
                                        placeholder="{{input}}"
                                        disabled={readOnly}
                                        compact={compact}
                                        rows={compact ? 2 : 3}
                                        label="Edit input"
                                    />
                                </div>
                            )}
                        </>
                    )}

                    {toolMode && (
                        <div className="space-y-1" data-ab-handle-anchor="toolset">
                            <Label>Actions</Label>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 w-full justify-between text-[11px]"
                                disabled={readOnly}
                                onClick={openActions}
                            >
                                <span>
                                    {(data.tool_exposure?.slug || typeMeta?.tool_exposure?.slug_prefix || 'call_agent')}
                                </span>
                                <span className="text-muted-foreground">Edit</span>
                            </Button>
                            <p className="text-[10px] text-muted-foreground">
                                Slug, description, and parameters for the supervisor tool call.
                            </p>
                        </div>
                    )}

                    {!toolMode && (
                        <>
                            <OutputKeyField
                                value={data.output_key}
                                defaultValue="agent_response"
                                onChange={(value) => updateField('output_key', value)}
                                readOnly={readOnly}
                                compact={compact}
                                hint="State key where the agent response is stored."
                            />
                            <div className="ab-flow-agent-response-row">
                                <span>Response</span>
                            </div>
                        </>
                    )}
                    <div data-ab-handle-anchor="response">
                        <StreamToggleField
                            stream={Boolean(data.stream)}
                            structured={Boolean(data.structured)}
                            readOnly={readOnly}
                            onChange={(patch) => onUpdate?.({ ...data, ...patch })}
                        />
                    </div>
                </>
            )}
            {showAdvanced && (
                <>
                    <StructuredOutputFields
                        structured={Boolean(data.structured)}
                        outputClass={data.output_class ?? ''}
                        outputClasses={outputClasses}
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
                            onCheckedChange={(checked) =>
                                updateField('parallel_tool_calls', Boolean(checked))
                            }
                            disabled={readOnly}
                            id={`parallel-tools-${node.id}`}
                        />
                        <Label htmlFor={`parallel-tools-${node.id}`}>Parallel tool calls (override)</Label>
                    </div>
                    <div className="space-y-2 border-t border-border pt-3">
                        <Label className="text-xs font-medium uppercase text-muted-foreground">
                            Memory override
                        </Label>
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
        </>
    );
}
