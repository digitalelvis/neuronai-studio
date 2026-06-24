import { useCallback, useState } from 'react';
import Composer from './Composer';
import MessageList from './MessageList';
import StudioPlayground from './StudioPlayground';
import { createId } from './utils/id';

function formatWorkflowOutput(output) {
    if (!output) {
        return 'Workflow completed.';
    }

    try {
        const filtered = { ...output };
        delete filtered.__steps;
        delete filtered.__current_node_id;
        delete filtered.__workflow_run_id;
        return JSON.stringify(filtered, null, 2);
    } catch {
        return String(output);
    }
}

export default function StudioChat({
    adapter,
    mode = 'agent',
    entityId,
    enableAttachments = false,
    showPlayground = true,
    initialContext = {},
    onRunCompleted,
}) {
    const [messages, setMessages] = useState([]);
    const [context, setContext] = useState(initialContext);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');

    const updateMessage = useCallback((id, patch) => {
        setMessages((current) =>
            current.map((message) => (message.id === id ? { ...message, ...patch } : message)),
        );
    }, []);

    const appendMessage = useCallback((message) => {
        setMessages((current) => [...current, message]);
        return message.id;
    }, []);

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
        });

        if (mode === 'workflow') {
            window.dispatchEvent(new CustomEvent('canvas-run-start'));
        }

        let assistantText = '';
        const toolMessages = [];

        try {
            for await (const packet of adapter.send(text, attachments, { state: context })) {
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
                    updateMessage(assistantId, { streaming: false, content: assistantText || 'Waiting for your input…' });
                    appendMessage({
                        id: createId('system'),
                        role: 'system',
                        content: packet.data?.prompt ?? 'Please provide input to continue.',
                        meta: { status: 'awaiting_input', nodeId: packet.data?.node_id, runId: packet.data?.run_id },
                    });
                    setSending(false);
                    return;
                }

                if (packet.event === 'run_completed') {
                    const outputText = formatWorkflowOutput(packet.data?.output);
                    updateMessage(assistantId, {
                        content: outputText,
                        streaming: false,
                        meta: { runId: packet.data?.run_id, status: 'completed' },
                    });
                    onRunCompleted?.(packet.data);
                }

                if (packet.event === 'done') {
                    updateMessage(assistantId, {
                        content: assistantText || 'Done.',
                        streaming: false,
                        meta: { toolEvents: toolMessages.length ? toolMessages : undefined },
                    });
                }

                if (packet.event === 'run_failed' || packet.event === 'error') {
                    const message = packet.data?.message ?? 'Run failed.';
                    setError(message);
                    updateMessage(assistantId, {
                        content: message,
                        streaming: false,
                        meta: { status: 'failed' },
                    });
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
            const message = sendError instanceof Error ? sendError.message : 'Request failed.';
            setError(message);
            updateMessage(assistantId, { content: message, streaming: false, meta: { status: 'failed' } });
        } finally {
            setSending(false);
        }
    };

    const handleClear = () => {
        setMessages([]);
        setError('');
        adapter?.reset?.();
    };

    return (
        <div className="ab-chat-shell">
            {showPlayground && (
                <StudioPlayground
                    mode={mode}
                    entityId={entityId}
                    context={context}
                    onContextChange={setContext}
                />
            )}
            <div className="ab-chat-toolbar">
                <button type="button" className="ab-btn ab-btn-sm" onClick={handleClear} disabled={sending}>
                    Clear
                </button>
                {error && <span className="ab-error ab-chat-error">{error}</span>}
            </div>
            <MessageList messages={messages} />
            <Composer disabled={sending} onSend={handleSend} enableAttachments={enableAttachments} />
        </div>
    );
}
