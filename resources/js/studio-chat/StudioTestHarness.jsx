import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Settings, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Separator } from '@/components/ui/separator';
import ChatTracesTabs from './ChatTracesTabs';
import PlaygroundTracesPanel from './PlaygroundTracesPanel';
import SettingsSheet from './SettingsSheet';
import StudioAgentInputPanel from './StudioAgentInputPanel';
import StudioChat from './StudioChat';
import ThreadSidebar from './ThreadSidebar';
import { createThreadId, getThreadFromUrl, setThreadInUrl } from './utils/thread';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';

function threadLabel(threadId, threads) {
    const match = threads.find((thread) => thread.id === threadId);
    return match?.label ?? (threadId ? `Session ${threadId.slice(0, 8)}` : 'New chat');
}

export default function StudioTestHarness({
    adapter,
    mode = 'agent',
    entityId,
    enableAttachments = false,
    uploadUrl = null,
    initialContext = {},
    onRunCompleted,
    agentMeta = null,
    embedded = false,
    threadHistoryUrl = null,
    threadsIndexUrl = null,
    tracesIndexUrl = null,
    threadRunsUrlTemplate = null,
    traceShowJsonUrlTemplate = null,
    traceShowUrlTemplate = null,
    showCloseButton = false,
    onClose,
}) {
    const chatRef = useRef(null);
    const [context, setContext] = useState(initialContext);
    const [instructions, setInstructions] = useState(agentMeta?.instructions ?? '');
    const [parameters, setParameters] = useState({});
    const [sending, setSending] = useState(false);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [agentInputsOpen, setAgentInputsOpen] = useState(false);
    const [mainTab, setMainTab] = useState('chat');
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [threads, setThreads] = useState([]);
    const [threadsLoading, setThreadsLoading] = useState(false);
    const [refreshToken, setRefreshToken] = useState(0);
    const [threadId, setThreadId] = useState(() => getThreadFromUrl() ?? createThreadId());

    const supportsThreads = mode === 'agent' || mode === 'workflow';

    const threadRunsUrl = useMemo(() => {
        if (mode !== 'agent' || !threadRunsUrlTemplate || !threadId) {
            return null;
        }

        return threadRunsUrlTemplate.replace('__THREAD__', encodeURIComponent(threadId));
    }, [mode, threadRunsUrlTemplate, threadId]);

    const loadThreads = useCallback(async () => {
        if (!threadsIndexUrl) {
            return;
        }

        setThreadsLoading(true);

        try {
            const response = await fetch(threadsIndexUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            setThreads(payload.data ?? []);
        } catch {
            // Keep existing list on failure.
        } finally {
            setThreadsLoading(false);
        }
    }, [threadsIndexUrl]);

    useEffect(() => {
        loadThreads();
    }, [loadThreads, refreshToken]);

    useEffect(() => {
        if (!supportsThreads) {
            return;
        }

        setThreadInUrl(threadId);
    }, [supportsThreads, threadId]);

    const bumpRefresh = useCallback(() => {
        setRefreshToken((token) => token + 1);
    }, []);

    const handleSelectThread = (nextThreadId) => {
        if (!nextThreadId || nextThreadId === threadId) {
            setMainTab('chat');
            return;
        }

        setThreadId(nextThreadId);
        setMainTab('chat');
        adapter?.reset?.();
    };

    const handleNewThread = () => {
        const nextThreadId = createThreadId();
        setThreadId(nextThreadId);
        setMainTab('chat');
        adapter?.reset?.();
        setThreadInUrl(nextThreadId);
    };

    const handleRunCompleted = useCallback(
        (...args) => {
            bumpRefresh();
            onRunCompleted?.(...args);
        },
        [bumpRefresh, onRunCompleted],
    );

    const chatProps = {
        adapter,
        mode,
        entityId,
        enableAttachments,
        uploadUrl,
        showPlayground: false,
        initialContext: context,
        onContextChange: setContext,
        onRunCompleted: handleRunCompleted,
        threadHistoryUrl,
        instructions,
        parameters,
        threadId,
        onThreadIdChange: setThreadId,
        hideHeader: true,
        onSendingChange: setSending,
        onActivity: bumpRefresh,
    };

    const activeLabel = threadLabel(threadId, threads);

    return (
        <TooltipProvider>
            <div className="flex h-full min-h-0 overflow-hidden bg-background">
                {supportsThreads && (
                    <ThreadSidebar
                        threads={threads}
                        activeThreadId={threadId}
                        onSelectThread={handleSelectThread}
                        onNewThread={handleNewThread}
                        collapsed={sidebarCollapsed}
                        onCollapsedChange={setSidebarCollapsed}
                        loading={threadsLoading}
                        disabled={sending}
                    />
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="relative flex shrink-0 items-center gap-3 border-b border-border px-4 py-2.5">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">{activeLabel}</p>
                            {threadId && (
                                <p className="truncate font-mono text-[10px] text-muted-foreground">{threadId}</p>
                            )}
                        </div>

                        <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                            <ChatTracesTabs value={mainTab} onValueChange={setMainTab} />
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5">
                            {mode === 'agent' && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => setAgentInputsOpen(true)}
                                >
                                    Inputs
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8"
                                onClick={() => setSettingsOpen(true)}
                            >
                                <Settings className="h-4 w-4" />
                                <span className="sr-only sm:not-sr-only sm:ml-1.5 sm:inline">Settings</span>
                            </Button>
                            {showCloseButton && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8"
                                    onClick={() => onClose?.()}
                                    title="Close playground"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    </header>

                    <div className="min-h-0 flex-1 overflow-hidden">
                        {mainTab === 'chat' ? (
                            <StudioChat ref={chatRef} {...chatProps} embedded={embedded} />
                        ) : (
                            <PlaygroundTracesPanel
                                mode={mode}
                                tracesIndexUrl={tracesIndexUrl}
                                threadRunsUrl={threadRunsUrl}
                                traceShowJsonUrlTemplate={traceShowJsonUrlTemplate}
                                traceShowUrlTemplate={traceShowUrlTemplate}
                                refreshToken={refreshToken}
                            />
                        )}
                    </div>
                </div>

                <SettingsSheet
                    open={settingsOpen}
                    onOpenChange={setSettingsOpen}
                    mode={mode}
                    entityId={entityId}
                    context={context}
                    onContextChange={setContext}
                    agentMeta={agentMeta}
                />

                {mode === 'agent' && (
                    <Sheet open={agentInputsOpen} onOpenChange={setAgentInputsOpen}>
                        <SheetContent className="flex w-full flex-col overflow-hidden sm:max-w-lg">
                            <SheetHeader>
                                <SheetTitle>Agent inputs</SheetTitle>
                                <SheetDescription>
                                    Override system prompt and model parameters for this playground session.
                                </SheetDescription>
                            </SheetHeader>
                            <Separator className="my-2" />
                            <div className="min-h-0 flex-1 overflow-auto py-2">
                                <StudioAgentInputPanel
                                    instructions={instructions}
                                    onInstructionsChange={setInstructions}
                                    context={context}
                                    onContextChange={setContext}
                                    parameters={parameters}
                                    onParametersChange={setParameters}
                                    onSend={(text, attachments) => {
                                        setAgentInputsOpen(false);
                                        setMainTab('chat');
                                        chatRef.current?.send?.(text, attachments);
                                    }}
                                    sending={sending}
                                    enableAttachments={enableAttachments}
                                    hideComposer
                                />
                            </div>
                        </SheetContent>
                    </Sheet>
                )}
            </div>
        </TooltipProvider>
    );
}
