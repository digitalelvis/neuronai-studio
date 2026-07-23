import { useEffect, useMemo, useState } from 'react';
import { Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import StudioTestHarness from '../../studio-chat/StudioTestHarness';
import PlaygroundShell from '../../studio-chat/PlaygroundShell';
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

            <PlaygroundShell open={open} onOpenChange={setOpen}>
                {workflowAdapter ? (
                    <StudioTestHarness
                        adapter={workflowAdapter}
                        mode="workflow"
                        entityId={workflowConfig.workflowId}
                        enableAttachments={Boolean(workflowConfig.uploadUrl)}
                        uploadUrl={workflowConfig.uploadUrl}
                        embedded
                        showCloseButton
                        onClose={() => setOpen(false)}
                        threadsIndexUrl={workflowConfig.threadsIndexUrl}
                        tracesIndexUrl={workflowConfig.tracesIndexUrl}
                        traceShowJsonUrlTemplate={workflowConfig.traceShowJsonUrlTemplate}
                        traceShowUrlTemplate={workflowConfig.traceShowUrlTemplate}
                        onRunCompleted={() =>
                            window.dispatchEvent(new CustomEvent('workflow-trace-finished'))
                        }
                    />
                ) : (
                    <p className="p-6 text-sm text-muted-foreground">Save the workflow first to enable testing.</p>
                )}
            </PlaygroundShell>
        </>
    );
}
