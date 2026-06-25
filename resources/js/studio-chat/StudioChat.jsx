import { useCallback, useEffect, useRef, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import Composer from './Composer';
import MessageList from './MessageList';
import ThreadBar from './ThreadBar';
import { createId } from './utils/id';
import { createThreadId, getThreadFromUrl, setThreadInUrl } from './utils/thread';
import { formatWorkflowData } from './utils/workflowOutput';

export default function StudioChat({
    adapter,
    mode = 'agent',
    entityId,
    enableAttachments = false,
    showPlayground = true,
    initialContext = {},
    onContextChange,
    onRunCompleted,
    embedded = false,
    threadHistoryUrl = null,
}) {
    const [messages, setMessages] = useState([]);
    const [threadId, setThreadId] = useState(() => getThreadFromUrl() ?? createThreadId());
    const historyLoadedRef = useRef(null);
    const [context, setContext] = useState(initialContext);
    const [inputJson, setInputJson] = useState(() => JSON.stringify(initialContext ?? {}, null, 2));
    const [inputJsonError, setInputJsonError] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [viewMode, setViewMode] = useState('pretty');

    const effectiveContext = onContextChange ? initialContext : context;
    const setEffectiveContext = onContextChange ?? setContext;

    const handleInputJsonChange = useCallback(
        (value) => {
            setInputJson(value);

            try {
                const parsed = JSON.parse(value || '{}');
                setInputJsonError('');
                setEffectiveContext(parsed);
            } catch {
                setInputJsonError('Invalid JSON');
            }
        },
        [setEffectiveContext],
    );

    const updateMessage = useCallback((id, patch) => {
        setMessages((current) =>
            current.map((message) => {
                if (message.id !== id) {
                    return message;
                }

                const nextPatch = typeof patch === 'function' ? patch(message) : patch;
                return { ...message, ...nextPatch };
            }),
        );
    }, []);

    const appendMessage = useCallback((message) => {
        setMessages((current) => [...current, message]);
        return message.id;
    }, []);

    useEffect(() => {
        if (mode !== 'agent') {
            return;
        }

        setThreadInUrl(threadId);
    }, [mode, threadId]);

    useEffect(() => {
        if (mode !== 'agent' || !threadHistoryUrl || !threadId) {
            return;
        }

        if (historyLoadedRef.current === threadId) {
            return;
        }

        historyLoadedRef.current = threadId;
        let cancelled = false;

        const url = threadHistoryUrl.replace('__THREAD__', encodeURIComponent(threadId));

        fetch(url, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (cancelled || !data?.messages?.length) {
                    return;
                }

                setMessages(
                    data.messages.map((message, index) => ({
                        id: createId(`history-${message.role}-${index}`),
                        role: message.role,
                        content: message.content,
                    })),
                );
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [mode, threadHistoryUrl, threadId]);

    const handleNewThread = () => {
        const nextThreadId = createThreadId();
        historyLoadedRef.current = nextThreadId;
        setThreadId(nextThreadId);
        setMessages([]);
        setError('');
        adapter?.reset?.();
        setThreadInUrl(nextThreadId);
    };

    const handleSend = async (text, attachments = []) => {
        if (!adapter || sending) {
            return;
        }

        setError('');
        setSending(true);

        appendMessage({
            id: createId('user'),
            role: 'user',
            content: text,
            attachments,
        });

        const assistantId = createId('assistant');
        appendMessage({
            id: assistantId,
            role: 'assistant',
            content: '',
            streaming: true,
            meta: mode === 'workflow' ? { userMessage: text, stepEvents: [] } : undefined,
        });

        if (mode === 'workflow') {
            window.dispatchEvent(new CustomEvent('canvas-trace-start'));
        }

        let assistantText = '';
        const toolMessages = [];
        let traceFinished = false;

        try {
            const sendContext =
                mode === 'agent'
                    ? { state: effectiveContext, threadId }
                    : { state: effectiveContext };

            for await (const packet of adapter.send(text, attachments, sendContext)) {
                if (packet.event === 'thread' && packet.data?.thread_id) {
                    setThreadId(packet.data.thread_id);
                }

                if (packet.event === 'trace_started' && mode === 'workflow') {
                    updateMessage(assistantId, {
                        content: '',
                        streaming: true,
                        meta: { userMessage: text, stepEvents: [], status: 'running' },
                    });
                }

                if (packet.event === 'step_started' && mode === 'workflow') {
                    updateMessage(assistantId, (current) => ({
                        streaming: true,
                        meta: {
                            ...current.meta,
                            userMessage: text,
                            currentNodeId: packet.data?.node_id ?? null,
                        },
                    }));
                }

                if (packet.event === 'step_completed' && mode === 'workflow') {
                    updateMessage(assistantId, (current) => {
                        const stepEvents = [...(current.meta?.stepEvents ?? [])];
                        stepEvents.push({
                            nodeId: packet.data?.node_id ?? 'unknown',
                            nodeType: packet.data?.node_type ?? 'unknown',
                            handle: packet.data?.handle,
                            durationMs: packet.data?.duration_ms ?? null,
                        });

                        return {
                            streaming: true,
                            meta: {
                                ...current.meta,
                                userMessage: text,
                                stepEvents,
                                currentNodeId: null,
                            },
                        };
                    });
                }

                if (packet.event === 'token') {
                    assistantText += packet.data?.delta ?? '';
                    updateMessage(assistantId, { content: assistantText, streaming: true });
                }

                if (packet.event === 'message') {
                    assistantText = packet.data?.content ?? assistantText;
                    updateMessage(assistantId, { content: assistantText, streaming: true });
                }

                if (packet.event === 'tool_call' || packet.event === 'tool_result') {
                    toolMessages.push({
                        name: packet.data?.name ?? 'tool',
                        type: packet.event === 'tool_call' ? 'call' : 'result',
                        inputs: packet.data?.inputs ?? {},
                        result: packet.data?.result ?? null,
                    });
                }

                if (packet.event === 'human_input_required') {
                    traceFinished = true;
                    updateMessage(assistantId, { streaming: false, content: assistantText || 'Waiting for your input…' });
                    appendMessage({
                        id: createId('system'),
                        role: 'system',
                        content: packet.data?.prompt ?? 'Please provide input to continue.',
                        meta: { status: 'awaiting_input', nodeId: packet.data?.node_id, traceId: packet.data?.trace_id },
                    });
                    setSending(false);
                    return;
                }

                if (packet.event === 'trace_completed') {
                    traceFinished = true;
                    const output = packet.data?.output;
                    updateMessage(assistantId, {
                        content: formatWorkflowData(output),
                        streaming: false,
                        meta: {
                            traceId: packet.data?.trace_id,
                            status: 'completed',
                            workflowOutput: output,
                            userMessage: text,
                            stepEvents: undefined,
                            currentNodeId: null,
                        },
                    });
                    onRunCompleted?.(packet.data);
                    window.dispatchEvent(new CustomEvent('workflow-trace-finished'));
                }

                if (packet.event === 'done') {
                    traceFinished = true;
                    updateMessage(assistantId, {
                        content: assistantText || 'Done.',
                        streaming: false,
                        meta: { toolEvents: toolMessages.length ? toolMessages : undefined },
                    });
                }

                if (packet.event === 'trace_failed' || packet.event === 'error') {
                    traceFinished = true;
                    const message = packet.data?.message ?? 'Trace failed.';
                    setError(message);
                    updateMessage(assistantId, {
                        content: message,
                        streaming: false,
                        meta: { status: 'failed' },
                    });
                    window.dispatchEvent(new CustomEvent('workflow-trace-finished'));
                }
            }

            if (assistantText) {
                updateMessage(assistantId, {
                    content: assistantText,
                    streaming: false,
                    meta: { toolEvents: toolMessages.length ? toolMessages : undefined },
                });
            }
        } catch (sendError) {
            traceFinished = true;
            const message = sendError instanceof Error ? sendError.message : 'Request failed.';
            setError(message);
            updateMessage(assistantId, { content: message, streaming: false, meta: { status: 'failed' } });
        } finally {
            setSending(false);

            if (!traceFinished) {
                setMessages((current) =>
                    current.map((message) =>
                        message.id === assistantId && message.streaming && !message.content && !message.meta?.stepEvents?.length
                            ? {
                                  ...message,
                                  content: 'No response received.',
                                  streaming: false,
                                  meta: { ...message.meta, status: 'failed' },
                              }
                            : message,
                    ),
                );
            }
        }
    };

    const handleClear = () => {
        if (mode === 'agent') {
            handleNewThread();
            return;
        }

        setMessages([]);
        setError('');
        adapter?.reset?.();
    };

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-2">
                {mode === 'agent' ? (
                    <ThreadBar threadId={threadId} onNewThread={handleNewThread} disabled={sending} />
                ) : (
                    <span className="text-sm font-medium text-muted-foreground">Output</span>
                )}
                <div className="flex shrink-0 items-center gap-2">
                    {mode === 'workflow' && (
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant={viewMode === 'pretty' ? 'secondary' : 'ghost'}
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => setViewMode('pretty')}
                            >
                                Pretty
                            </Button>
                            <Button
                                type="button"
                                variant={viewMode === 'data' ? 'secondary' : 'ghost'}
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => setViewMode('data')}
                            >
                                Data
                            </Button>
                        </div>
                    )}
                    {error && <span className="text-xs text-destructive">{error}</span>}
                    {mode !== 'agent' && (
                        <Button variant="ghost" size="sm" onClick={handleClear} disabled={sending}>
                            <Trash2 className="h-4 w-4" />
                            Clear
                        </Button>
                    )}
                </div>
            </div>

            <ScrollArea className="flex-1 px-4">
                <MessageList messages={messages} mode={mode} viewMode={viewMode} />
            </ScrollArea>

            <div className="border-t border-border p-4">
                <Composer
                    disabled={sending}
                    onSend={handleSend}
                    enableAttachments={enableAttachments}
                    enableInputJson={mode === 'workflow'}
                    inputJson={inputJson}
                    onInputJsonChange={handleInputJsonChange}
                    inputJsonError={inputJsonError}
                />
            </div>
        </div>
    );
}
