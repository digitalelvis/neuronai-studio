import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { buildPartialWorkflowThread, buildWorkflowPrettyThread, TRUNCATE_LENGTH } from './utils/workflowOutput';

function ThreadEntry({ entry }) {
    const [expanded, setExpanded] = useState(false);
    const content = entry.content ?? '';
    const truncated = content.length > TRUNCATE_LENGTH;
    const displayContent = truncated && !expanded ? `${content.slice(0, TRUNCATE_LENGTH)}…` : content;

    return (
        <div className="border-b border-border/60 py-3 last:border-b-0">
            <div className="mb-1.5 flex items-center gap-2">
                <span className="font-mono text-xs font-medium text-foreground">{entry.label}</span>
                <Badge variant="outline" className="text-[10px] uppercase">
                    {entry.nodeType}
                </Badge>
                {entry.durationMs != null && (
                    <span className="text-[10px] text-muted-foreground">{entry.durationMs}ms</span>
                )}
                {entry.running && <span className="animate-pulse text-[10px] text-primary">running</span>}
            </div>
            {entry.pending && !entry.content ? (
                <p className="text-xs text-muted-foreground italic">Completed step</p>
            ) : (
                <>
                    {entry.key && (
                        <p className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">{entry.key}</p>
                    )}
                    <div className="whitespace-pre-wrap text-sm text-foreground">{displayContent}</div>
                    {truncated && (
                        <Button
                            type="button"
                            variant="link"
                            size="sm"
                            className="h-auto px-0 py-0 text-xs"
                            onClick={() => setExpanded((current) => !current)}
                        >
                            {expanded ? 'Show less' : 'Show more'}
                        </Button>
                    )}
                </>
            )}
        </div>
    );
}

export default function WorkflowThread({
    output,
    userMessage = '',
    stepEvents = [],
    currentNodeId = null,
    streaming = false,
    className,
}) {
    const thread =
        output && !streaming
            ? buildWorkflowPrettyThread(output, userMessage)
            : buildPartialWorkflowThread(stepEvents, userMessage, currentNodeId);

    if (!thread.length) {
        return <p className="text-sm text-muted-foreground">Running workflow…</p>;
    }

    return (
        <div className={cn('divide-y divide-border/60', className)}>
            {thread.map((entry, index) => (
                <ThreadEntry key={`${entry.nodeId}-${entry.key ?? index}`} entry={entry} />
            ))}
        </div>
    );
}
