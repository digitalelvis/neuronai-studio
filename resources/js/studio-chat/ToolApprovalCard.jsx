import { useState } from 'react';
import { Check, ShieldAlert, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

function PendingTool({ tool }) {
    const args = tool?.arguments ?? {};
    const hasArgs = args && typeof args === 'object' && Object.keys(args).length > 0;

    return (
        <div className="rounded-md border border-amber-500/30 bg-background/60 p-2">
            <div className="flex items-center gap-2">
                <Badge variant="outline" className="font-mono text-[10px]">
                    {tool?.name ?? 'tool'}
                </Badge>
            </div>
            {hasArgs && (
                <pre className="mt-2 overflow-x-auto rounded bg-muted/40 p-2 text-[11px]">
                    {JSON.stringify(args, null, 2)}
                </pre>
            )}
        </div>
    );
}

export default function ToolApprovalCard({ message, disabled = false, onDecision }) {
    const [feedback, setFeedback] = useState('');
    const pendingTools = message.meta?.pendingTools ?? [];
    const resolved = message.meta?.resolution;

    if (resolved) {
        return (
            <div className="mt-2 flex items-center gap-2 text-xs">
                {resolved === 'approve' ? (
                    <Badge variant="secondary" className="text-[10px]">
                        <Check className="mr-1 h-3 w-3" />
                        Approved
                    </Badge>
                ) : (
                    <Badge variant="destructive" className="text-[10px]">
                        <X className="mr-1 h-3 w-3" />
                        Rejected
                    </Badge>
                )}
            </div>
        );
    }

    const decide = (decision) => {
        if (disabled) {
            return;
        }

        onDecision?.(message.id, decision, feedback);
    };

    return (
        <div className="mt-2 space-y-3">
            <div className="flex items-center gap-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                <ShieldAlert className="h-4 w-4" />
                Tool approval required
            </div>

            {pendingTools.length > 0 && (
                <div className="space-y-2">
                    {pendingTools.map((tool, index) => (
                        <PendingTool key={`${message.id}-pending-${index}`} tool={tool} />
                    ))}
                </div>
            )}

            <Textarea
                value={feedback}
                onChange={(event) => setFeedback(event.target.value)}
                placeholder="Optional instructions for the agent (used on reject)…"
                disabled={disabled}
                className="min-h-[52px] text-xs"
            />

            <div className="flex gap-2">
                <Button type="button" size="sm" className="h-8" disabled={disabled} onClick={() => decide('approve')}>
                    <Check className="h-4 w-4" />
                    Approve
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    className="h-8"
                    disabled={disabled}
                    onClick={() => decide('reject')}
                >
                    <X className="h-4 w-4" />
                    Reject
                </Button>
            </div>
        </div>
    );
}
