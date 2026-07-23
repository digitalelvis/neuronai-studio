import { useCallback, useEffect, useState } from 'react';
import { ChevronUp, Terminal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TraceList, TraceDetailSheet } from '../../studio-traces';

export default function LogsDrawer({
    workflowConfig = {},
    validationMessage = '',
}) {
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState('traces');
    const [selectedTraceId, setSelectedTraceId] = useState(null);
    const [traceSheetOpen, setTraceSheetOpen] = useState(false);
    const [refreshToken, setRefreshToken] = useState(0);
    const [runEvents, setRunEvents] = useState([]);

    useEffect(() => {
        const onOpen = () => {
            setOpen(true);
            setTab('traces');
        };
        window.addEventListener('workflow-open-traces', onOpen);
        return () => window.removeEventListener('workflow-open-traces', onOpen);
    }, []);

    useEffect(() => {
        if (validationMessage) {
            setOpen(true);
            setTab('validation');
        }
    }, [validationMessage]);

    useEffect(() => {
        const onRunStart = () => {
            setRunEvents([{ id: Date.now(), text: 'Run started', level: 'info' }]);
            setOpen(true);
            setTab('events');
        };

        const onExecution = (event) => {
            const detail = event.detail || {};
            const text =
                detail.event === 'step_started'
                    ? `Started ${detail.node_id || 'node'}`
                    : detail.event === 'step_completed'
                      ? `Completed ${detail.node_id || 'node'}`
                      : detail.event === 'trace_failed'
                        ? 'Run failed'
                        : detail.event === 'trace_completed'
                          ? 'Run completed'
                          : detail.event || 'Event';

            setRunEvents((current) => [
                ...current.slice(-99),
                {
                    id: `${Date.now()}-${current.length}`,
                    text,
                    level: detail.event === 'trace_failed' ? 'error' : 'info',
                    nodeId: detail.node_id,
                },
            ]);
        };

        const onFinished = () => setRefreshToken((n) => n + 1);

        window.addEventListener('canvas-run-start', onRunStart);
        window.addEventListener('canvas-trace-start', onRunStart);
        window.addEventListener('canvas-execution-event', onExecution);
        window.addEventListener('workflow-trace-finished', onFinished);

        return () => {
            window.removeEventListener('canvas-run-start', onRunStart);
            window.removeEventListener('canvas-trace-start', onRunStart);
            window.removeEventListener('canvas-execution-event', onExecution);
            window.removeEventListener('workflow-trace-finished', onFinished);
        };
    }, []);

    const focusNode = useCallback((nodeId) => {
        if (!nodeId) {
            return;
        }

        window.dispatchEvent(new CustomEvent('canvas-focus-node', { detail: { id: nodeId } }));
    }, []);

    return (
        <div className={`ab-logs-dock ${open ? 'ab-logs-dock--open' : ''}`}>
            <Button
                variant="secondary"
                size="sm"
                className="ab-fab ab-fab-logs gap-1.5 shadow-lg"
                onClick={() => setOpen((value) => !value)}
            >
                <Terminal className="h-3.5 w-3.5" />
                Logs
                <ChevronUp className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
            </Button>

            {open && (
                <div className="ab-logs-panel">
                    <Tabs value={tab} onValueChange={setTab} className="flex h-full flex-col">
                        <TabsList className="mx-2 mt-2 grid w-auto grid-cols-3">
                            <TabsTrigger value="traces">Traces</TabsTrigger>
                            <TabsTrigger value="events">Events</TabsTrigger>
                            <TabsTrigger value="validation">Validation</TabsTrigger>
                        </TabsList>

                        <TabsContent value="traces" className="mt-0 min-h-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                            <TraceList
                                tracesIndexUrl={workflowConfig.tracesIndexUrl}
                                selectedTraceId={selectedTraceId}
                                onSelectTrace={(trace) => {
                                    setSelectedTraceId(trace.id);
                                    setTraceSheetOpen(true);
                                }}
                                refreshToken={refreshToken}
                            />
                        </TabsContent>

                        <TabsContent value="events" className="mt-0 min-h-0 flex-1 overflow-auto p-3 data-[state=inactive]:hidden">
                            {runEvents.length === 0 ? (
                                <p className="text-xs text-muted-foreground">Run the playground to see live events.</p>
                            ) : (
                                <ul className="space-y-1.5">
                                    {runEvents.map((event) => (
                                        <li key={event.id}>
                                            <button
                                                type="button"
                                                className={`w-full rounded-md px-2 py-1.5 text-left text-xs ${event.level === 'error' ? 'bg-destructive/10 text-destructive' : 'bg-muted/40 text-foreground'} ${event.nodeId ? 'hover:bg-muted/70' : ''}`}
                                                onClick={() => focusNode(event.nodeId)}
                                                disabled={!event.nodeId}
                                            >
                                                {event.text}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </TabsContent>

                        <TabsContent value="validation" className="mt-0 min-h-0 flex-1 overflow-auto p-3 data-[state=inactive]:hidden">
                            {validationMessage ? (
                                <p className="text-xs text-muted-foreground whitespace-pre-wrap">{validationMessage}</p>
                            ) : (
                                <p className="text-xs text-muted-foreground">No validation messages. Use Validate in the header.</p>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            )}

            <TraceDetailSheet
                open={traceSheetOpen}
                onOpenChange={setTraceSheetOpen}
                traceId={selectedTraceId}
                traceShowJsonUrlTemplate={workflowConfig.traceShowJsonUrlTemplate}
                traceShowUrlTemplate={workflowConfig.traceShowUrlTemplate}
            />
        </div>
    );
}
