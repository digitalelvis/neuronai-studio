import { useEffect, useState } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import TraceDetailViewer from './TraceDetailViewer';
import { fetchTrace, resolveTraceUrl } from './traceApi';

export default function TraceDetailSheet({
    open,
    onOpenChange,
    traceId,
    traceShowJsonUrlTemplate,
    traceShowUrlTemplate,
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    useEffect(() => {
        if (!open || !traceId || !traceShowJsonUrlTemplate) {
            return;
        }

        let cancelled = false;

        async function load() {
            setLoading(true);
            setError('');

            try {
                const url = resolveTraceUrl(traceShowJsonUrlTemplate, traceId);
                const payload = await fetchTrace(url);
                if (!cancelled) {
                    setDetail(payload);
                }
            } catch (loadError) {
                if (!cancelled) {
                    setError(loadError instanceof Error ? loadError.message : 'Failed to load trace.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();

        return () => {
            cancelled = true;
        };
    }, [open, traceId, traceShowJsonUrlTemplate]);

    const traceShowUrl = traceShowUrlTemplate ? resolveTraceUrl(traceShowUrlTemplate, traceId) : null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
                <SheetHeader className="sr-only">
                    <SheetTitle>Trace #{traceId}</SheetTitle>
                    <SheetDescription>Workflow trace details</SheetDescription>
                </SheetHeader>

                {loading && <p className="p-4 text-sm text-muted-foreground">Loading trace…</p>}
                {error && <p className="p-4 text-sm text-destructive">{error}</p>}

                {!loading && !error && detail && (
                    <TraceDetailViewer
                        variant="sheet"
                        trace={{
                            id: detail.trace.id,
                            status: detail.trace.status,
                            workflowName: detail.trace.workflow_name,
                            errorMessage: detail.trace.error_message,
                            input: detail.trace.input,
                            output: detail.trace.output,
                            durationMs: detail.trace.duration_ms,
                        }}
                        steps={detail.steps ?? []}
                        traceShowUrl={traceShowUrl}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
