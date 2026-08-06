import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import NodeConfigForm from './NodeConfigForm';
import { NodeTypeIcon } from '../nodes/nodeIcons';

export default function NodeInspectorSidebar({
    editingNode,
    section = 'all',
    onClose,
    onUpdate,
    onRemove,
    agents = [],
    workflows = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    variables = [],
    defaultProvider = '',
    defaultModel = '',
    nodeTypesMeta = {},
    readOnly = false,
}) {
    if (!editingNode) {
        return null;
    }

    const nodeType = editingNode.type ?? 'node';
    const typeMeta = nodeTypesMeta[nodeType] || {};
    const title = typeMeta.label || nodeType;
    const icon = typeMeta.icon;

    return (
        <aside className="ab-node-inspector flex h-full min-h-0 flex-col bg-background">
            <div className="flex shrink-0 items-start gap-3 border-b border-border px-4 py-3">
                <span
                    className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md"
                    style={{
                        background: 'color-mix(in srgb, var(--primary) 14%, transparent)',
                        color: 'var(--primary)',
                    }}
                >
                    <NodeTypeIcon name={icon} className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold capitalize text-foreground">{title}</div>
                    <div className="truncate text-xs text-muted-foreground">{nodeType}</div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 shrink-0"
                    onClick={onClose}
                    title="Close"
                >
                    <X className="h-4 w-4" />
                </Button>
            </div>

            <ScrollArea className="min-h-0 flex-1">
                <div className="px-4 py-4">
                    {nodeType === 'start' || nodeType === 'stop' ? (
                        <p className="text-sm text-muted-foreground">
                            {nodeType === 'start'
                                ? 'Entry point of the workflow. No additional configuration.'
                                : 'Terminal node. No additional configuration.'}
                        </p>
                    ) : (
                        <NodeConfigForm
                            node={editingNode}
                            agents={agents}
                            workflows={workflows}
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
                            nodeTypesMeta={nodeTypesMeta}
                            readOnly={readOnly}
                            onUpdate={readOnly ? undefined : onUpdate}
                            onRemove={readOnly ? undefined : onRemove}
                            section={section}
                            showRemove
                            showType={false}
                        />
                    )}
                </div>
            </ScrollArea>
        </aside>
    );
}
