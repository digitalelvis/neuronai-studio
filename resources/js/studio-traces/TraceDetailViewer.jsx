import { useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import TraceStepTimeline from './TraceStepTimeline';
import TraceStepDetail from './TraceStepDetail';
import { formatDuration } from './TraceListItem';
import { formatCost, formatTokens } from '@/lib/formatUsage';

export default function TraceDetailViewer({ trace, steps = [], variant = 'page', traceShowUrl = null }) {
    const [selectedStepId, setSelectedStepId] = useState(steps?.[0]?.id ?? null);
    const selectedStep = steps.find((step) => step.id === selectedStepId) ?? steps[0];

    return (
        <div className="flex h-full min-h-0 flex-col bg-background">
            <div className="shrink-0 border-b border-border px-4 py-3">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm font-medium">Trace #{trace?.id}</span>
                    {trace?.status && <Badge variant={trace.status}>{trace.status}</Badge>}
                    {trace?.workflowName && <span className="text-sm text-muted-foreground">{trace.workflowName}</span>}
                    {trace?.durationMs != null && (
                        <span className="text-xs text-muted-foreground">{formatDuration(trace.durationMs)}</span>
                    )}
                    <span className="text-xs text-muted-foreground">
                        {formatTokens(trace?.totalTokens)} ({trace?.promptTokens ?? 0} prompt / {trace?.completionTokens ?? 0} completion)
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {formatCost(trace?.estimatedCost, trace?.currency)}
                    </span>
                    {variant === 'sheet' && traceShowUrl && (
                        <Button variant="ghost" size="sm" className="ml-auto h-7 text-xs" asChild>
                            <a href={traceShowUrl} target="_blank" rel="noreferrer">
                                Open full page
                                <ExternalLink className="ml-1 h-3 w-3" />
                            </a>
                        </Button>
                    )}
                </div>
                {trace?.errorMessage && <p className="mt-2 text-sm text-destructive">{trace.errorMessage}</p>}
            </div>

            <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                <ResizablePanel defaultSize={35} minSize={25}>
                    <TraceStepTimeline
                        steps={steps}
                        selectedStepId={selectedStep?.id}
                        onSelectStep={setSelectedStepId}
                    />
                </ResizablePanel>
                <ResizableHandle withHandle />
                <ResizablePanel defaultSize={65} minSize={40}>
                    <TraceStepDetail trace={trace} selectedStep={selectedStep} />
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    );
}
