import { useEffect, useMemo, useState } from 'react';
import { Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import StudioTestHarness from '../../studio-chat/StudioTestHarness';
import { WorkflowSessionAdapter } from '../../studio-chat/adapters/WorkflowSessionAdapter';

export default function PlaygroundOverlay({ workflowConfig = {}, onBeforeRun }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onOpen = () => setOpen(true);
        window.addEventListener('workflow-open-test', onOpen);
        return () => window.removeEventListener('workflow-open-test', onOpen);
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
    }, [
        workflowConfig.streamUrl,
        workflowConfig.resumeUrlTemplate,
        workflowConfig.uploadUrl,
        onBeforeRun,
    ]);

    return (
        <>
            <Button
                size="sm"
                className="ab-fab ab-fab-playground gap-1.5 shadow-lg"
                onClick={() => setOpen(true)}
                disabled={!workflowConfig.workflowId}
                title={!workflowConfig.workflowId ? 'Save the workflow first' : 'Open playground'}
            >
                <Play className="h-3.5 w-3.5" />
                Playground
            </Button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg"
                >
                    <SheetHeader className="shrink-0 space-y-1 border-b border-border px-4 py-3 text-left">
                        <SheetTitle>Playground</SheetTitle>
                        <SheetDescription>Run and chat with this workflow</SheetDescription>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-hidden">
                        {workflowAdapter ? (
                            <StudioTestHarness
                                adapter={workflowAdapter}
                                mode="workflow"
                                entityId={workflowConfig.workflowId}
                                enableAttachments={Boolean(workflowConfig.uploadUrl)}
                                uploadUrl={workflowConfig.uploadUrl}
                                embedded
                                onRunCompleted={() =>
                                    window.dispatchEvent(new CustomEvent('workflow-trace-finished'))
                                }
                            />
                        ) : (
                            <p className="p-4 text-sm text-muted-foreground">
                                Save the workflow first to enable testing.
                            </p>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
