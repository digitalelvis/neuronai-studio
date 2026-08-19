import { useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { t } from '@/lib/i18n';
import NodeConfigForm from './NodeConfigForm';
import { NodeTypeIcon } from '../nodes/nodeIcons';
import { nodeTitleUniquenessKey, normalizeNodeTitle } from '../graph';

export default function NodeInspectorSidebar({
    editingNode,
    section = 'all',
    onClose,
    onUpdate,
    onTitleChange,
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
    const nodeType = editingNode?.type ?? 'node';
    const typeMeta = nodeTypesMeta[nodeType] || {};
    const icon = typeMeta.icon;
    const typeLabel = editingNode?.typeLabel || typeMeta.label || nodeType;
    const committedTitle = normalizeNodeTitle(editingNode?.title) || typeLabel;
    const [draftTitle, setDraftTitle] = useState(committedTitle);
    const [titleError, setTitleError] = useState('');
    const revertRef = useRef(committedTitle);

    useEffect(() => {
        const next = committedTitle;
        revertRef.current = next;
        setDraftTitle(next);
        setTitleError('');
    }, [editingNode?.id, committedTitle]);

    if (!editingNode) {
        return null;
    }

    const commitTitle = () => {
        if (readOnly) {
            setDraftTitle(revertRef.current);
            return;
        }

        const trimmed = draftTitle.trim();

        if (trimmed === '') {
            setDraftTitle(revertRef.current);
            setTitleError('');
            return;
        }

        const nextKey = nodeTitleUniquenessKey(trimmed);
        const conflict = (editingNode.existingTitles || []).some(
            (title) => nodeTitleUniquenessKey(title) === nextKey,
        );

        if (conflict) {
            setTitleError(t('inspector.node_name_duplicate', 'This node name is already used in this workflow.'));
            setDraftTitle(revertRef.current);
            return;
        }

        setTitleError('');
        revertRef.current = trimmed;
        setDraftTitle(trimmed);

        if (normalizeNodeTitle(editingNode.title) !== trimmed) {
            onTitleChange?.(trimmed);
        }
    };

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
                    <input
                        type="text"
                        className="w-full truncate border-0 border-b border-transparent bg-transparent p-0 text-sm font-semibold text-foreground outline-none focus:border-primary read-only:cursor-default"
                        value={draftTitle}
                        readOnly={readOnly}
                        maxLength={80}
                        placeholder={t('inspector.node_name_placeholder', 'Node name')}
                        aria-label={t('inspector.node_name_placeholder', 'Node name')}
                        onChange={(event) => {
                            setDraftTitle(event.target.value);
                            setTitleError('');
                        }}
                        onBlur={commitTitle}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                event.currentTarget.blur();
                            }

                            if (event.key === 'Escape') {
                                event.preventDefault();
                                setDraftTitle(revertRef.current);
                                setTitleError('');
                                event.currentTarget.blur();
                            }
                        }}
                    />
                    {titleError ? (
                        <div className="mt-1 text-xs text-destructive">{titleError}</div>
                    ) : null}
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
                        section={section}
                        onUpdate={onUpdate}
                        onRemove={onRemove}
                    />
                </div>
            </ScrollArea>
        </aside>
    );
}
