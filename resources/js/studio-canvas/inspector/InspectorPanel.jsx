import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import GraphJsonPanel from '../GraphJsonPanel';
import WorkflowCodePanel from '../WorkflowCodePanel';
import { TraceList, TraceDetailSheet } from '../../studio-traces';
import ConnectPanel from '../../components/ConnectPanel';

export default function InspectorPanel({
    workflowConfig = {},
}) {
    const [tab, setTab] = useState('test');
    const [selectedTraceId, setSelectedTraceId] = useState(null);
    const [traceSheetOpen, setTraceSheetOpen] = useState(false);
    const [tracesRefreshToken, setTracesRefreshToken] = useState(0);

    useEffect(() => {
        const onOpenTest = () => setTab('test');
        window.addEventListener('workflow-open-test', onOpenTest);
        return () => window.removeEventListener('workflow-open-test', onOpenTest);
    }, []);

    useEffect(() => {
        const onOpenTraces = () => setTab('traces');
        window.addEventListener('workflow-open-traces', onOpenTraces);
        return () => window.removeEventListener('workflow-open-traces', onOpenTraces);
    }, []);

    useEffect(() => {
        const onOpenCode = () => setTab('code');
        window.addEventListener('workflow-open-code', onOpenCode);
        return () => window.removeEventListener('workflow-open-code', onOpenCode);
    }, []);

    useEffect(() => {
        const onViewTrace = (event) => {
            const traceId = event.detail?.traceId;
            if (!traceId) {
                return;
            }

            setSelectedTraceId(traceId);
            setTraceSheetOpen(true);
            setTab('traces');
        };

        window.addEventListener('workflow-view-trace', onViewTrace);
        return () => window.removeEventListener('workflow-view-trace', onViewTrace);
    }, []);

    useEffect(() => {
        const onTraceFinished = () => setTracesRefreshToken((current) => current + 1);
        window.addEventListener('workflow-trace-finished', onTraceFinished);
        return () => window.removeEventListener('workflow-trace-finished', onTraceFinished);
    }, []);

    const handleTraceSelect = (trace) => {
        setSelectedTraceId(trace.id);
        setTraceSheetOpen(true);
    };

    const canPreview = workflowConfig.canPreview !== false;
    const canExport = workflowConfig.canExport !== false;

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden bg-card">
            <Tabs value={tab} onValueChange={setTab} className="flex h-full flex-col">
                <TabsList className={`mx-2 mt-2 grid w-auto ${canPreview ? 'grid-cols-5' : 'grid-cols-4'}`}>
                    <TabsTrigger value="test">Test</TabsTrigger>
                    <TabsTrigger value="traces">Trace</TabsTrigger>
                    <TabsTrigger value="json">JSON</TabsTrigger>
                    {canPreview && <TabsTrigger value="code">Code</TabsTrigger>}
                    <TabsTrigger value="connect">Connect</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="test"
                    forceMount
                    className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden"
                >
                    <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                        <p className="text-sm text-muted-foreground">
                            Open the Playground to chat, manage sessions, and inspect traces.
                        </p>
                        <Button
                            size="sm"
                            disabled={!workflowConfig.workflowId}
                            onClick={() => window.dispatchEvent(new CustomEvent('workflow-open-test'))}
                        >
                            Open Playground
                        </Button>
                    </div>
                </TabsContent>

                <TabsContent value="traces" className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                    <TraceList
                        tracesIndexUrl={workflowConfig.tracesIndexUrl}
                        selectedTraceId={selectedTraceId}
                        onSelectTrace={handleTraceSelect}
                        refreshToken={tracesRefreshToken}
                    />
                </TabsContent>

                <TabsContent value="json" className="mt-0 flex-1 overflow-hidden p-2 data-[state=inactive]:hidden">
                    <GraphJsonPanel readOnly={workflowConfig.readOnly ?? false} />
                </TabsContent>

                {canPreview && (
                    <TabsContent value="code" className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                        <WorkflowCodePanel
                            readOnly={workflowConfig.readOnly ?? false}
                            canExport={canExport}
                            canPreview={canPreview}
                        />
                    </TabsContent>
                )}

                <TabsContent value="connect" className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                    <ConnectPanel
                        protocols={workflowConfig.enabledProtocols ?? ['vercel', 'agui']}
                        streamUrls={workflowConfig.integrateStreamUrls ?? {}}
                        resumeUrls={workflowConfig.integrateResumeUrls ?? {}}
                        type="workflow"
                    />
                </TabsContent>
            </Tabs>

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
