import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { cn } from '@/lib/utils';

function JsonBlock({ data }) {
    const text = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
    return <pre className="overflow-x-auto rounded-md border border-border bg-background p-4 font-mono text-xs">{text}</pre>;
}

export default function RunDetailViewer({ config }) {
    const [selectedStepId, setSelectedStepId] = useState(config.steps?.[0]?.id ?? null);
    const run = config.run ?? {};
    const steps = config.steps ?? [];
    const selectedStep = steps.find((s) => s.id === selectedStepId) ?? steps[0];

    return (
        <div className="flex h-[calc(100vh-3rem)] flex-col bg-background">
            <div className="border-b border-border px-4 py-3">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm font-medium">Run #{run.id}</span>
                    <Badge variant={run.status}>{run.status}</Badge>
                    {run.workflowName && <span className="text-sm text-muted-foreground">{run.workflowName}</span>}
                </div>
                {run.errorMessage && <p className="mt-2 text-sm text-destructive">{run.errorMessage}</p>}
            </div>

            <ResizablePanelGroup direction="horizontal" className="flex-1">
                <ResizablePanel defaultSize={35} minSize={25}>
                    <div className="flex h-full flex-col border-r border-border">
                        <div className="border-b border-border px-4 py-2 text-xs font-medium uppercase text-muted-foreground">Timeline</div>
                        <ScrollArea className="flex-1">
                            <div className="space-y-1 p-2">
                                {steps.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">No steps recorded.</p>
                                ) : (
                                    steps.map((step) => (
                                        <button
                                            key={step.id}
                                            type="button"
                                            onClick={() => setSelectedStepId(step.id)}
                                            className={cn(
                                                'flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/50',
                                                selectedStep?.id === step.id && 'bg-muted',
                                            )}
                                        >
                                            <span>
                                                <span className="font-medium">{step.node_type}</span>
                                                <span className="ml-2 text-xs text-muted-foreground">{step.node_id}</span>
                                            </span>
                                            {step.duration_ms != null && (
                                                <span className="text-xs text-muted-foreground">{step.duration_ms}ms</span>
                                            )}
                                        </button>
                                    ))
                                )}
                            </div>
                        </ScrollArea>
                    </div>
                </ResizablePanel>
                <ResizableHandle withHandle />
                <ResizablePanel defaultSize={65} minSize={40}>
                    <Tabs defaultValue="input" className="flex h-full flex-col p-4">
                        <TabsList>
                            <TabsTrigger value="input">Input</TabsTrigger>
                            <TabsTrigger value="output">Output</TabsTrigger>
                            <TabsTrigger value="step">Step State</TabsTrigger>
                        </TabsList>
                        <TabsContent value="input" className="mt-3 flex-1 overflow-auto">
                            <JsonBlock data={run.input} />
                        </TabsContent>
                        <TabsContent value="output" className="mt-3 flex-1 overflow-auto">
                            <JsonBlock data={run.output} />
                        </TabsContent>
                        <TabsContent value="step" className="mt-3 flex-1 overflow-auto">
                            {selectedStep ? (
                                <Collapsible defaultOpen>
                                    <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md border border-border px-3 py-2 text-sm hover:bg-muted/30">
                                        <span>{selectedStep.node_type} — {selectedStep.node_id}</span>
                                        <ChevronDown className="h-4 w-4" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="pt-3">
                                        <JsonBlock data={selectedStep.state_snapshot} />
                                    </CollapsibleContent>
                                </Collapsible>
                            ) : (
                                <p className="text-sm text-muted-foreground">Select a step from the timeline.</p>
                            )}
                        </TabsContent>
                    </Tabs>
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    );
}
