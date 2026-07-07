import { useCallback, useEffect, useMemo, useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import GraphJsonPanel from '../GraphJsonPanel';
import WorkflowCodePanel from '../WorkflowCodePanel';
import StudioTestHarness from '../../studio-chat/StudioTestHarness';
import { WorkflowSessionAdapter } from '../../studio-chat/adapters/WorkflowSessionAdapter';
import { TraceList, TraceDetailSheet } from '../../studio-traces';
import ConnectPanel from '../../components/ConnectPanel';

export default function InspectorPanel({
    workflowConfig = {},
    onBeforeRun,
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

    const workflowAdapter = useMemo(() => {
        if (!workflowConfig.streamUrl) {
            return null;
        }

        return new WorkflowSessionAdapter({
            streamUrl: workflowConfig.streamUrl,
            resumeUrlTemplate: workflowConfig.resumeUrlTemplate,
            uploadUrl: workflowConfig.uploadUrl,
            onBeforeRun,
            syncCanvas: true,
        });
    }, [workflowConfig.streamUrl, workflowConfig.resumeUrlTemplate, workflowConfig.uploadUrl, onBeforeRun]);

    const handleTraceSelect = (trace) => {
        setSelectedTraceId(trace.id);
        setTraceSheetOpen(true);
    };

    const handleTraceFinished = useCallback(() => {
        setTracesRefreshToken((current) => current + 1);
        window.dispatchEvent(new CustomEvent('workflow-trace-finished'));
    }, []);

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden bg-card">
            <Tabs value={tab} onValueChange={setTab} className="flex h-full flex-col">
                <TabsList className="mx-2 mt-2 grid w-auto grid-cols-5">
                    <TabsTrigger value="test">Test</TabsTrigger>
                    <TabsTrigger value="traces">Trace</TabsTrigger>
                    <TabsTrigger value="json">JSON</TabsTrigger>
                    <TabsTrigger value="code">Code</TabsTrigger>
                    <TabsTrigger value="connect">Connect</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="test"
                    forceMount
                    className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden"
                >
                    {workflowAdapter ? (
                        <StudioTestHarness
                            adapter={workflowAdapter}
                            mode="workflow"
                            entityId={workflowConfig.workflowId}
                            enableAttachments={Boolean(workflowConfig.uploadUrl)}
                            uploadUrl={workflowConfig.uploadUrl}
                            embedded
                            onRunCompleted={handleTraceFinished}
                        />
                    ) : (
                        <p className="p-4 text-sm text-muted-foreground">Save the workflow first to enable testing.</p>
                    )}
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

                <TabsContent value="code" className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                    <WorkflowCodePanel readOnly={workflowConfig.readOnly ?? false} />
                </TabsContent>

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
