import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import GraphJsonPanel from '../GraphJsonPanel';
import StudioTestHarness from '../../studio-chat/StudioTestHarness';
import { WorkflowSessionAdapter } from '../../studio-chat/adapters/WorkflowSessionAdapter';
import { TraceList, TraceDetailSheet } from '../../studio-traces';
import NodeConfigForm from './NodeConfigForm';

function normalizeNode(node) {
    if (!node) {
        return null;
    }

    const data = { ...(node.data || {}) };

    if (node.type === 'agent' && data.agent_id != null && data.agent_id !== '') {
        data.agent_id = String(data.agent_id);
    }

    if (node.type === 'tool' || node.type === 'mcp') {
        if (!data.output_key) {
            data.output_key = node.type === 'mcp' ? 'mcp_result' : 'tool_result';
        }
        if (data.parameters && !data.parameters_json) {
            data.parameters_json = JSON.stringify(data.parameters, null, 2);
        }
    }

    if (node.type === 'human' && !data.output_key) {
        data.output_key = 'human_response';
    }

    if (node.type === 'condition' && !data.operator) {
        data.operator = 'not_empty';
    }

    if (node.type === 'llm') {
        if (!data.output_key) {
            data.output_key = 'llm_response';
        }
    }

    return { ...node, data };
}

export default function InspectorPanel({
    agents = [],
    tools = [],
    mcpServers = [],
    providers = {},
    providerModels = {},
    defaultProvider = '',
    defaultModel = '',
    readOnly = false,
    workflowConfig = {},
    onBeforeRun,
}) {
    const [tab, setTab] = useState('node');
    const [selectedNode, setSelectedNode] = useState(null);
    const [selectedTraceId, setSelectedTraceId] = useState(null);
    const [traceSheetOpen, setTraceSheetOpen] = useState(false);
    const [tracesRefreshToken, setTracesRefreshToken] = useState(0);
    const testRunningRef = useRef(false);

    useEffect(() => {
        const onSelect = (event) => {
            const detail = event.detail ?? {};

            if (detail.id) {
                setSelectedNode(normalizeNode(detail));
            }

            if (!detail.silent && !testRunningRef.current) {
                setTab('node');
            }
        };

        window.addEventListener('canvas-node-selected', onSelect);
        return () => window.removeEventListener('canvas-node-selected', onSelect);
    }, []);

    useEffect(() => {
        const onTestStart = () => {
            testRunningRef.current = true;
        };
        const onTestFinish = () => {
            testRunningRef.current = false;
        };

        window.addEventListener('canvas-trace-start', onTestStart);
        window.addEventListener('workflow-trace-finished', onTestFinish);

        return () => {
            window.removeEventListener('canvas-trace-start', onTestStart);
            window.removeEventListener('workflow-trace-finished', onTestFinish);
        };
    }, []);

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
        const flush = () => {
            if (selectedNode) {
                window.dispatchEvent(
                    new CustomEvent('canvas-node-updated', {
                        detail: { id: selectedNode.id, data: { ...selectedNode.data } },
                    }),
                );
            }
        };

        window.addEventListener('canvas-inspector-flush', flush);
        return () => window.removeEventListener('canvas-inspector-flush', flush);
    }, [selectedNode]);

    const syncNode = useCallback(
        (data) => {
            if (!selectedNode) {
                return;
            }

            setSelectedNode((current) => ({ ...current, data }));

            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: { id: selectedNode.id, data },
                }),
            );
        },
        [selectedNode],
    );

    const removeNode = () => {
        window.dispatchEvent(new CustomEvent('canvas-remove-node'));
        setSelectedNode(null);
    };

    const workflowAdapter = useMemo(() => {
        if (!workflowConfig.streamUrl) {
            return null;
        }

        return new WorkflowSessionAdapter({
            streamUrl: workflowConfig.streamUrl,
            resumeUrlTemplate: workflowConfig.resumeUrlTemplate,
            onBeforeRun,
            syncCanvas: true,
        });
    }, [workflowConfig.streamUrl, workflowConfig.resumeUrlTemplate, onBeforeRun]);

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
                <TabsList className="mx-2 mt-2 grid w-auto grid-cols-4">
                    <TabsTrigger value="node">Node</TabsTrigger>
                    <TabsTrigger value="json">JSON</TabsTrigger>
                    <TabsTrigger value="test">Test</TabsTrigger>
                    <TabsTrigger value="traces">Traces</TabsTrigger>
                </TabsList>

                <TabsContent value="node" className="mt-0 flex-1 overflow-hidden data-[state=inactive]:hidden">
                    <ScrollArea className="h-full p-4">
                        {readOnly && selectedNode && (
                            <p className="mb-3 text-xs text-muted-foreground">Read-only preview.</p>
                        )}
                        <NodeConfigForm
                            node={selectedNode}
                            agents={agents}
                            tools={tools}
                            mcpServers={mcpServers}
                            providers={providers}
                            providerModels={providerModels}
                            defaultProvider={defaultProvider}
                            defaultModel={defaultModel}
                            readOnly={readOnly}
                            onUpdate={readOnly ? undefined : syncNode}
                            onRemove={readOnly ? undefined : removeNode}
                        />
                    </ScrollArea>
                </TabsContent>

                <TabsContent value="json" className="mt-0 flex-1 overflow-hidden p-2 data-[state=inactive]:hidden">
                    <GraphJsonPanel readOnly={readOnly} />
                </TabsContent>

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
