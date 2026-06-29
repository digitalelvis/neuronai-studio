import { useState } from 'react';
import { Copy, Plus, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export default function ThreadBar({ threadId, onNewThread, disabled = false }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        if (!threadId) {
            return;
        }

        try {
            await navigator.clipboard.writeText(threadId);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div className="flex min-w-0 flex-1 items-center gap-2">
            <span className="shrink-0 text-xs text-muted-foreground">Thread</span>
            <code
                className={cn(
                    'min-w-0 truncate rounded bg-muted px-2 py-0.5 font-mono text-[11px] text-foreground',
                    !threadId && 'text-muted-foreground',
                )}
                title={threadId ?? 'Generating thread…'}
            >
                {threadId ?? '…'}
            </code>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-7 shrink-0 px-2"
                onClick={handleCopy}
                disabled={!threadId || disabled}
                title="Copy thread ID"
            >
                {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7 shrink-0 text-xs"
                onClick={onNewThread}
                disabled={disabled}
            >
                <Plus className="mr-1 h-3.5 w-3.5" />
                New Thread
            </Button>
        </div>
    );
}
