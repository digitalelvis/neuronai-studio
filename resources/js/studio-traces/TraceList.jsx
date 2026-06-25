import { useCallback, useEffect, useState } from 'react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import TraceListItem from './TraceListItem';
import { fetchTraces } from './traceApi';

export default function TraceList({
    tracesIndexUrl,
    variant = 'compact',
    selectedTraceId = null,
    onSelectTrace,
    refreshToken = 0,
}) {
    const [traces, setTraces] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const loadTraces = useCallback(async () => {
        if (!tracesIndexUrl) {
            return;
        }

        setLoading(true);
        setError('');

        try {
            const payload = await fetchTraces(tracesIndexUrl);
            setTraces(payload.data ?? []);
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Failed to load traces.');
        } finally {
            setLoading(false);
        }
    }, [tracesIndexUrl]);

    useEffect(() => {
        loadTraces();
    }, [loadTraces, refreshToken]);

    useEffect(() => {
        const onTraceFinished = () => loadTraces();
        window.addEventListener('workflow-trace-finished', onTraceFinished);
        return () => window.removeEventListener('workflow-trace-finished', onTraceFinished);
    }, [loadTraces]);

    if (!tracesIndexUrl) {
        return <p className="p-4 text-sm text-muted-foreground">Save the workflow first to view traces.</p>;
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex shrink-0 items-center justify-between border-b border-border px-3 py-2">
                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Traces</span>
                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={loadTraces} disabled={loading}>
                    <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                </Button>
            </div>

            {error && <p className="px-3 py-2 text-xs text-destructive">{error}</p>}

            <ScrollArea className="flex-1">
                <div className="space-y-1 p-2">
                    {!loading && traces.length === 0 && (
                        <p className="p-4 text-sm text-muted-foreground">No traces yet. Run a test to create one.</p>
                    )}
                    {traces.map((trace) => (
                        <TraceListItem
                            key={trace.id}
                            trace={trace}
                            variant={variant}
                            selected={selectedTraceId === trace.id}
                            onClick={onSelectTrace}
                        />
                    ))}
                </div>
            </ScrollArea>
        </div>
    );
}
