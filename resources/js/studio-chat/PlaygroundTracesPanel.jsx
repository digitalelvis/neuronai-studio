import { useCallback, useEffect, useState } from 'react';
import { RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { TraceList, TraceDetailSheet } from '../studio-traces';
import { formatCost, formatTokens } from '@/lib/formatUsage';
import { cn } from '@/lib/utils';

function formatDuration(ms) {
    if (ms == null) {
        return null;
    }

    if (ms < 1000) {
        return `${ms}ms`;
    }

    return `${(ms / 1000).toFixed(1)}s`;
}

function AgentRunsList({ runsIndexUrl, refreshToken = 0 }) {
    const [runs, setRuns] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [selected, setSelected] = useState(null);

    const loadRuns = useCallback(async () => {
        if (!runsIndexUrl) {
            setRuns([]);
            return;
        }

        setLoading(true);
        setError('');

        try {
            const response = await fetch(runsIndexUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('Failed to load runs.');
            }
            const payload = await response.json();
            setRuns(payload.data ?? []);
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Failed to load runs.');
        } finally {
            setLoading(false);
        }
    }, [runsIndexUrl]);

    useEffect(() => {
        loadRuns();
    }, [loadRuns, refreshToken]);

    if (!runsIndexUrl) {
        return (
            <div className="flex h-full items-center justify-center p-6 text-center">
                <p className="text-sm text-muted-foreground">Start a chat to create runs for this thread.</p>
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex shrink-0 items-center justify-between border-b border-border px-3 py-2">
                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Thread runs</span>
                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={loadRuns} disabled={loading}>
                    <RefreshCw className={cn('h-3.5 w-3.5', loading && 'animate-spin')} />
                </Button>
            </div>

            {error && <p className="px-3 py-2 text-xs text-destructive">{error}</p>}

            <ScrollArea className="flex-1">
                <div className="space-y-1 p-2">
                    {!loading && runs.length === 0 && (
                        <p className="p-4 text-sm text-muted-foreground">No runs yet for this thread.</p>
                    )}
                    {runs.map((run) => (
                        <button
                            key={run.id}
                            type="button"
                            onClick={() => setSelected(run)}
                            className={cn(
                                'w-full rounded-md border border-transparent px-3 py-2 text-left transition-colors hover:bg-muted/60',
                                selected?.id === run.id && 'border-border bg-muted/40',
                            )}
                        >
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="text-[10px]">
                                    {run.status}
                                </Badge>
                                {run.duration_ms != null && (
                                    <span className="text-[11px] text-muted-foreground">{formatDuration(run.duration_ms)}</span>
                                )}
                                {run.total_tokens != null && (
                                    <span className="text-[11px] text-muted-foreground">{formatTokens(run.total_tokens)}</span>
                                )}
                                <span className="text-[11px] text-muted-foreground">
                                    {formatCost(run.estimated_cost, run.currency)}
                                </span>
                            </div>
                            {run.input_preview && (
                                <p className="mt-1 truncate text-xs text-muted-foreground">{run.input_preview}</p>
                            )}
                        </button>
                    ))}
                </div>
            </ScrollArea>

            {selected && (
                <div className="shrink-0 border-t border-border p-3">
                    <p className="mb-1 text-[11px] font-medium uppercase text-muted-foreground">Selected run</p>
                    <pre className="max-h-48 overflow-auto rounded-md bg-muted/40 p-2 font-mono text-[11px]">
                        {JSON.stringify(selected, null, 2)}
                    </pre>
                </div>
            )}
        </div>
    );
}

export default function PlaygroundTracesPanel({
    mode = 'agent',
    tracesIndexUrl = null,
    threadRunsUrl = null,
    traceShowJsonUrlTemplate = null,
    traceShowUrlTemplate = null,
    refreshToken = 0,
}) {
    const [selectedTraceId, setSelectedTraceId] = useState(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const handleSelectTrace = (traceOrId) => {
        const traceId = typeof traceOrId === 'object' && traceOrId !== null ? traceOrId.id : traceOrId;
        setSelectedTraceId(traceId);
        setDetailOpen(true);
    };

    if (mode === 'workflow') {
        return (
            <div className="flex h-full min-h-0 flex-col">
                <TraceList
                    tracesIndexUrl={tracesIndexUrl}
                    variant="compact"
                    selectedTraceId={selectedTraceId}
                    onSelectTrace={handleSelectTrace}
                    refreshToken={refreshToken}
                />
                <TraceDetailSheet
                    open={detailOpen}
                    onOpenChange={setDetailOpen}
                    traceId={selectedTraceId}
                    traceShowJsonUrlTemplate={traceShowJsonUrlTemplate}
                    traceShowUrlTemplate={traceShowUrlTemplate}
                />
            </div>
        );
    }

    return <AgentRunsList runsIndexUrl={threadRunsUrl} refreshToken={refreshToken} />;
}
