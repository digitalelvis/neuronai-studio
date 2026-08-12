import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useCanvasUi } from '../CanvasUiContext';
import AgentNodeFields from './node-forms/AgentNodeFields';
import LlmNodeFields from './node-forms/LlmNodeFields';
import IntentClassifierNodeFields from './node-forms/IntentClassifierNodeFields';
import HumanNodeFields from './node-forms/HumanNodeFields';
import RunWorkflowNodeFields from './node-forms/RunWorkflowNodeFields';
import SetStateNodeFields from './node-forms/SetStateNodeFields';
import InvokeNodeFields from './node-forms/InvokeNodeFields';
import ConditionNodeFields from './node-forms/ConditionNodeFields';
import SwitchNodeFields from './node-forms/SwitchNodeFields';
import LoopNodeFields from './node-forms/LoopNodeFields';
import ForkNodeFields from './node-forms/ForkNodeFields';
import JoinNodeFields from './node-forms/JoinNodeFields';
import DelayNodeFields from './node-forms/DelayNodeFields';
import ToolNodeFields from './node-forms/ToolNodeFields';
import McpNodeFields from './node-forms/McpNodeFields';
import RagNodeFields from './node-forms/RagNodeFields';
import StopNodeFields from './node-forms/StopNodeFields';

const NODE_FIELDS = {
    agent: AgentNodeFields,
    llm: LlmNodeFields,
    intent_classifier: IntentClassifierNodeFields,
    human: HumanNodeFields,
    run_workflow: RunWorkflowNodeFields,
    set_state: SetStateNodeFields,
    invoke: InvokeNodeFields,
    condition: ConditionNodeFields,
    switch: SwitchNodeFields,
    loop: LoopNodeFields,
    fork: ForkNodeFields,
    join: JoinNodeFields,
    delay: DelayNodeFields,
    tool: ToolNodeFields,
    mcp: McpNodeFields,
    rag: RagNodeFields,
    stop: StopNodeFields,
};

export default function NodeConfigForm({
    node,
    agents,
    workflows = [],
    tools,
    mcpServers,
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    variables: variablesProp,
    defaultProvider = '',
    defaultModel = '',
    nodeTypesMeta: nodeTypesMetaProp,
    readOnly,
    onUpdate,
    onRemove,
    section = 'all',
    compact = false,
    showRemove = true,
    showType = true,
}) {
    const canvasUi = useCanvasUi();
    const nodeTypesMeta = nodeTypesMetaProp || canvasUi.nodeTypesMeta || {};
    const workflowOptions = workflows.length > 0 ? workflows : canvasUi.workflows || [];
    // Prefer explicit prop: sidebar inspector lives outside CanvasUiProvider.
    const variables = Array.isArray(variablesProp) ? variablesProp : canvasUi.variables || [];

    if (!node) {
        return <p className="text-sm text-muted-foreground">Select a node to configure it.</p>;
    }

    const data = node.data || {};
    const showControls = section === 'all' || section === 'controls';
    const showAdvanced = section === 'all' || section === 'advanced';
    const typeMeta = nodeTypesMeta[node.type] || {};
    const canRemove = showRemove && !readOnly && !['start', 'stop'].includes(node.type);
    const Fields = NODE_FIELDS[node.type];

    return (
        <div className={compact ? 'space-y-3' : 'space-y-4'}>
            {showType && (
                <div>
                    <Label className="text-xs text-muted-foreground">Type</Label>
                    <p className="text-sm font-medium capitalize">{node.type}</p>
                </div>
            )}

            {Fields && (
                <Fields
                    node={node}
                    data={data}
                    agents={agents}
                    workflows={workflowOptions}
                    tools={tools}
                    mcpServers={mcpServers}
                    knowledgeBases={knowledgeBases}
                    ragSearchUrlTemplate={ragSearchUrlTemplate}
                    outputClasses={outputClasses}
                    providers={providers}
                    providerModels={providerModels}
                    variables={variables}
                    defaultProvider={defaultProvider}
                    defaultModel={defaultModel}
                    typeMeta={typeMeta}
                    readOnly={readOnly}
                    compact={compact}
                    showControls={showControls}
                    showAdvanced={showAdvanced}
                    onUpdate={onUpdate}
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
