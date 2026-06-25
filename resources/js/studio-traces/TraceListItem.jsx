import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

function formatRelativeTime(isoString) {
    if (!isoString) {
        return '—';
    }

    const date = new Date(isoString);
    const diffMs = Date.now() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 60) {
        return 'just now';
    }

    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) {
        return `${diffMin}m ago`;
    }

    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) {
        return `${diffHr}h ago`;
    }

    return date.toLocaleDateString();
}

function formatDuration(ms) {
    if (ms == null) {
        return '—';
    }

    if (ms < 1000) {
        return `${ms}ms`;
    }

    return `${(ms / 1000).toFixed(2)}s`;
}

export default function TraceListItem({ trace, selected = false, onClick, variant = 'compact' }) {
    return (
        <button
            type="button"
            onClick={() => onClick?.(trace)}
            className={cn(
                'flex w-full flex-col gap-1 rounded-md border border-transparent px-3 py-2 text-left transition-colors hover:bg-muted/50',
                selected && 'border-border bg-muted',
                variant === 'compact' && 'text-sm',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-medium">Trace #{trace.id}</span>
                <Badge variant={trace.status}>{trace.status}</Badge>
            </div>
            {trace.input_preview && (
                <p className="truncate text-xs text-muted-foreground">{trace.input_preview}</p>
            )}
            <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                <span>{formatRelativeTime(trace.started_at)}</span>
                <span>{formatDuration(trace.duration_ms)}</span>
            </div>
        </button>
    );
}

export { formatRelativeTime, formatDuration };
