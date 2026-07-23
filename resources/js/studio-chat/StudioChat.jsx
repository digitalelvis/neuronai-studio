import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import Composer from './Composer';
import MessageList from './MessageList';
import ThreadBar from './ThreadBar';
import { createId } from './utils/id';
import { createThreadId, getThreadFromUrl, setThreadInUrl } from './utils/thread';
import { formatWorkflowData } from './utils/workflowOutput';
import { uploadAttachments } from './utils/uploadAttachments';

function toDisplayAttachments(attachments, uploaded = []) {
    return attachments.map((attachment, index) => {
        const stored = uploaded[index];

        return {
            ...attachment,
            id: attachment.id ?? `${attachment.name}-${index}`,
            storageKey: stored?.storage_key ?? attachment.storageKey,
            mimeType: stored?.mime_type ?? attachment.mimeType,
            url: stored?.url ?? attachment.url,
        };
    });
}

function usageFromPayload(data) {
    if (data?.total_tokens == null) {
        return undefined;
    }

    return {
        promptTokens: data.prompt_tokens ?? 0,
        completionTokens: data.completion_tokens ?? 0,
        totalTokens: data.total_tokens ?? 0,
        estimatedCost: data.estimated_cost ?? '0.000000',
        currency: data.currency ?? 'USD',
    };
}

export default forwardRef(function StudioChat({
    adapter,
    mode = 'agent',
    entityId,
    enableAttachments = false,
    uploadUrl = null,
    showPlayground = true,
    initialContext = {},
    onContextChange,
    onRunCompleted,
    embedded = false,
    threadHistoryUrl = null,
    hideComposer = false,
    hideHeader = false,
    instructions = '',
    parameters = {},
    onSendingChange,
    threadId: controlledThreadId,
    onThreadIdChange,
    onActivity,
}, ref) {
    const [messages, setMessages] = useState([]);
    const [uncontrolledThreadId, setUncontrolledThreadId] = useState(() => getThreadFromUrl() ?? createThreadId());
    const isThreadControlled = controlledThreadId !== undefined && controlledThreadId !== null;
    const threadId = isThreadControlled ? controlledThreadId : uncontrolledThreadId;
    const setThreadId = useCallback(
        (next) => {
            const value = typeof next === 'function' ? next(threadId) : next;
            if (!isThreadControlled) {
                setUncontrolledThreadId(value);
            }
            onThreadIdChange?.(value);
        },
        [isThreadControlled, onThreadIdChange, threadId],
    );
    const historyLoadedRef = useRef(null);
    const [context, setContext] = useState(initialContext);
    const [inputJson, setInputJson] = useState(() => JSON.stringify(initialContext ?? {}, null, 2));
    const [inputJsonError, setInputJsonError] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [viewMode, setViewMode] = useState('pretty');
    const supportsThreads = mode === 'agent' || mode === 'workflow';

    useEffect(() => {
        onSendingChange?.(sending);
    }, [onSendingChange, sending]);

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

    const consumeAssistantStream = useCallback(
        async (iterator, assistantId, userText, initialText = '') => {
            let assistantText = initialText;
            let traceFinished = false;
            const toolMessages = [];

            for await (const packet of iterator) {
                if (packet.event === 'thread' && packet.data?.thread_id) {
                    setThreadId(packet.data.thread_id);
                }

                if (packet.event === 'trace_started' && mode === 'workflow') {
                    updateMessage(assistantId, {
                        content: '',
                        streaming: true,
                        meta: { userMessage: userText, stepEvents: [], status: 'running' },
                    });
                }

                if (packet.event === 'step_started' && mode === 'workflow') {
                    updateMessage(assistantId, (current) => ({
                        streaming: true,
                        meta: {
                            ...current.meta,
                            userMessage: userText,
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
                            usage: usageFromPayload(packet.data),
                        });

                        return {
                            streaming: true,
                            meta: {
                                ...current.meta,
                                userMessage: userText,
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
                    return { traceFinished, assistantText };
                }

                if (packet.event === 'tool_approval_required') {
                    traceFinished = true;
                    updateMessage(assistantId, {
                        streaming: false,
                        content: assistantText || 'Waiting for tool approval…',
                    });
                    appendMessage({
                        id: createId('tool-approval'),
                        role: 'system',
                        content: packet.data?.message ?? 'A tool requires your approval before running.',
                        meta: {
                            status: 'awaiting_tool_approval',
                            nodeId: packet.data?.node_id,
                            traceId: packet.data?.trace_id,
                            pendingTools: packet.data?.pending_tools ?? [],
                        },
                    });
                    return { traceFinished, assistantText };
                }

                if (packet.event === 'tool_approval_resolved') {
                    window.dispatchEvent(new CustomEvent('canvas-trace-start'));
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
                            userMessage: userText,
                            stepEvents: undefined,
                            currentNodeId: null,
                            usage: usageFromPayload(packet.data),
                            toolEvents: toolMessages.length ? toolMessages : undefined,
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
                        meta: {
                            status: 'completed',
                            usage: usageFromPayload(packet.data),
                            toolEvents: toolMessages.length ? toolMessages : undefined,
                        },
                    });
                }

                if (packet.event === 'trace_failed' || packet.event === 'error') {
                    traceFinished = true;
                    const failMessage = packet.data?.message ?? 'Trace failed.';
                    const traceId = packet.data?.trace_id ?? null;
                    setError(failMessage);
                    updateMessage(assistantId, (current) => ({
                        content: failMessage,
                        streaming: false,
                        meta: {
                            ...current.meta,
                            status: 'failed',
                            traceId,
                            toolEvents: toolMessages.length ? toolMessages : current.meta?.toolEvents,
                        },
                    }));
                    if (traceId) {
                        window.dispatchEvent(
                            new CustomEvent('workflow-view-trace', { detail: { traceId } }),
                        );
                    }
                    window.dispatchEvent(new CustomEvent('workflow-trace-finished'));
                }
            }

            if (assistantText && !traceFinished) {
                updateMessage(assistantId, {
                    content: assistantText,
                    streaming: false,
                    meta: { toolEvents: toolMessages.length ? toolMessages : undefined },
                });
            }

            return { traceFinished, assistantText };
        },
        [appendMessage, mode, onRunCompleted, updateMessage],
    );

    useEffect(() => {
        if (!supportsThreads) {
            return;
        }

        setThreadInUrl(threadId);
    }, [supportsThreads, threadId]);

    useEffect(() => {
        if (!isThreadControlled) {
            return;
        }

        if (historyLoadedRef.current === threadId) {
            return;
        }

        setMessages([]);
        setError('');
        historyLoadedRef.current = null;
    }, [isThreadControlled, threadId]);

    useEffect(() => {
        if ((mode !== 'agent' && mode !== 'workflow') || !threadHistoryUrl || !threadId) {
            return;
        }

        if (mode === 'workflow' && !threadHistoryUrl.includes('__THREAD__')) {
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

        let uploaded = [];
        let payloadAttachments = attachments;
        let traceFinished = false;
        let assistantId = null;

        try {
            if (enableAttachments && attachments.length > 0 && uploadUrl) {
                uploaded = await uploadAttachments(attachments, uploadUrl);
                payloadAttachments = uploaded.map((item, index) => ({
                    ...attachments[index],
                    storageKey: item.storage_key,
                    mimeType: item.mime_type,
                    url: item.url,
                }));
            }

            appendMessage({
                id: createId('user'),
                role: 'user',
                content: text,
                attachments: toDisplayAttachments(attachments, uploaded),
            });

            assistantId = createId('assistant');
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

            const sendContext = supportsThreads
                ? {
                      state: effectiveContext,
                      threadId,
                      instructions: mode === 'agent' ? instructions : undefined,
                      parameters: mode === 'agent' ? parameters : undefined,
                  }
                : {
                      state: effectiveContext,
                      instructions: mode === 'agent' ? instructions : undefined,
                      parameters: mode === 'agent' ? parameters : undefined,
                  };

            const result = await consumeAssistantStream(
                adapter.send(text, uploaded.length > 0 ? payloadAttachments : attachments, sendContext),
                assistantId,
                text,
            );
            traceFinished = result.traceFinished;
        } catch (sendError) {
            traceFinished = true;
            const message = sendError instanceof Error ? sendError.message : 'Request failed.';
            setError(message);
            if (assistantId) {
                updateMessage(assistantId, { content: message, streaming: false, meta: { status: 'failed' } });
            }
        } finally {
            setSending(false);
            onActivity?.();

            if (!traceFinished && assistantId) {
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

    const handleToolApproval = async (cardId, decision, feedback = '') => {
        if (!adapter || sending || typeof adapter.resumeApproval !== 'function') {
            return;
        }

        setError('');
        setSending(true);
        updateMessage(cardId, (current) => ({ meta: { ...current.meta, resolution: decision } }));

        const assistantId = createId('assistant');
        appendMessage({
            id: assistantId,
            role: 'assistant',
            content: '',
            streaming: true,
            meta: mode === 'workflow' ? { userMessage: '', stepEvents: [], status: 'running' } : undefined,
        });

        if (mode === 'workflow') {
            window.dispatchEvent(new CustomEvent('canvas-trace-start'));
        }

        let traceFinished = false;

        try {
            const result = await consumeAssistantStream(
                adapter.resumeApproval(decision, feedback),
                assistantId,
                '',
            );
            traceFinished = result.traceFinished;
        } catch (resumeError) {
            traceFinished = true;
            const message = resumeError instanceof Error ? resumeError.message : 'Request failed.';
            setError(message);
            updateMessage(assistantId, { content: message, streaming: false, meta: { status: 'failed' } });
        } finally {
            setSending(false);
            onActivity?.();

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
        if (supportsThreads) {
            handleNewThread();
            return;
        }

        setMessages([]);
        setError('');
        adapter?.reset?.();
    };

    useImperativeHandle(ref, () => ({
        send: handleSend,
        sending,
    }));

    return (
        <div className="flex h-full flex-col">
           

            <ScrollArea className="flex-1 px-4">
                <MessageList
                    messages={messages}
                    mode={mode}
                    viewMode={viewMode}
                    onToolApproval={handleToolApproval}
                    approvalDisabled={sending}
                />
            </ScrollArea>

            {!hideComposer && (
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
            )}
        </div>
    );
});
