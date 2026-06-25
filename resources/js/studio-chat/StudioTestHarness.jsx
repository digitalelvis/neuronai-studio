import { useState } from 'react';
import { Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { TooltipProvider } from '@/components/ui/tooltip';
import AgentMetaBar from './AgentMetaBar';
import SettingsSheet from './SettingsSheet';
import StudioChat from './StudioChat';
import StudioPlayground from './StudioPlayground';

export default function StudioTestHarness({
    adapter,
    mode = 'agent',
    entityId,
    enableAttachments = false,
    initialContext = {},
    onRunCompleted,
    agentMeta = null,
    embedded = false,
    threadHistoryUrl = null,
}) {
    const [context, setContext] = useState(initialContext);
    const [settingsOpen, setSettingsOpen] = useState(false);

    const chatProps = {
        adapter,
        mode,
        entityId,
        enableAttachments,
        showPlayground: false,
        initialContext: context,
        onContextChange: setContext,
        onRunCompleted,
        threadHistoryUrl,
    };

    if (embedded) {
        return (
            <TooltipProvider>
                <div className="flex h-full flex-col">
                    <StudioChat {...chatProps} embedded />
                </div>
            </TooltipProvider>
        );
    }

    return (
        <TooltipProvider>
            <div className="flex h-full flex-col bg-background">
                {agentMeta && <AgentMetaBar meta={agentMeta} />}

            <div className="flex shrink-0 items-center justify-end gap-2 border-b border-border px-4 py-2">
                    <Button variant="ghost" size="sm" onClick={() => setSettingsOpen(true)}>
                        <Settings className="h-4 w-4" />
                        Settings
                    </Button>
                </div>

                <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                    <ResizablePanel defaultSize={30} minSize={20} maxSize={45}>
                        <div className="flex h-full flex-col overflow-hidden p-4">
                            <StudioPlayground
                                mode={mode}
                                entityId={entityId}
                                context={context}
                                onContextChange={setContext}
                                variant="panel"
                            />
                        </div>
                    </ResizablePanel>
                    <ResizableHandle withHandle />
                    <ResizablePanel defaultSize={70} minSize={40}>
                        <div className="flex h-full flex-col overflow-hidden">
                            <StudioChat {...chatProps} />
                        </div>
                    </ResizablePanel>
                </ResizablePanelGroup>

                <SettingsSheet
                    open={settingsOpen}
                    onOpenChange={setSettingsOpen}
                    mode={mode}
                    entityId={entityId}
                    context={context}
                    onContextChange={setContext}
                    agentMeta={agentMeta}
                />
            </div>
        </TooltipProvider>
    );
}
