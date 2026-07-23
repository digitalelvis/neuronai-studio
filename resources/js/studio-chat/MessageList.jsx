import { useState } from 'react';
import { Bot, ChevronDown, MessageSquare, Sparkles, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import WorkflowThread from './WorkflowThread';
import ToolApprovalCard from './ToolApprovalCard';
import { formatWorkflowData } from './utils/workflowOutput';
import { formatCost, formatTokens } from '@/lib/formatUsage';

function AttachmentPreview({ attachment }) {
    const src = attachment.previewUrl || attachment.url;

    if (attachment.type === 'image' && src) {
        return (
            <img
                src={src}
                alt={attachment.name}
                className="mt-2 max-h-32 rounded-md border border-border"
            />
        );
    }

    return (
        <span className="mt-2 inline-block text-xs text-muted-foreground">
            {attachment.type}: {attachment.name}
        </span>
    );
}

function ToolEventBlock({ tool }) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="mt-2 rounded-md border border-border bg-muted/30">
            <CollapsibleTrigger className="flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-muted/50">
                <span className="font-medium">{tool.name}</span>
                <span className="flex items-center gap-2 text-muted-foreground">
                    <Badge variant="outline" className="text-[10px]">
                        {tool.type}
                    </Badge>
                    <ChevronDown className={cn('h-3 w-3 transition-transform', open && 'rotate-180')} />
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-2 border-t border-border px-3 py-2">
                {tool.inputs && Object.keys(tool.inputs).length > 0 && (
                    <div>
                        <p className="mb-1 text-[10px] uppercase text-muted-foreground">Input</p>
                        <pre className="overflow-x-auto rounded bg-background p-2 text-[11px]">{JSON.stringify(tool.inputs, null, 2)}</pre>
                    </div>
                )}
                {tool.result != null && (
                    <div>
                        <p className="mb-1 text-[10px] uppercase text-muted-foreground">Result</p>
                        <pre className="overflow-x-auto rounded bg-background p-2 text-[11px]">{String(tool.result)}</pre>
                    </div>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

function WorkflowAssistantContent({ message, viewMode }) {
    const isWorkflowResult =
        message.meta?.workflowOutput != null || message.meta?.stepEvents != null || message.meta?.status === 'running';

    if (viewMode === 'data' && message.meta?.workflowOutput) {
        return (
            <pre className="overflow-x-auto whitespace-pre-wrap rounded-md border border-border bg-muted/20 p-3 font-mono text-xs">
                {formatWorkflowData(message.meta.workflowOutput)}
            </pre>
        );
    }

    if (viewMode === 'pretty' && isWorkflowResult) {
        return (
            <WorkflowThread
                output={message.meta?.workflowOutput}
                userMessage={message.meta?.userMessage ?? ''}
                stepEvents={message.meta?.stepEvents ?? []}
                currentNodeId={message.meta?.currentNodeId ?? null}
                streaming={message.streaming}
            />
        );
    }

    if (viewMode === 'data' && message.content) {
        return (
            <pre className="overflow-x-auto whitespace-pre-wrap rounded-md border border-border bg-muted/20 p-3 font-mono text-xs">
                {message.content}
            </pre>
        );
    }

    return (
        <div className="whitespace-pre-wrap text-sm leading-relaxed">
            {message.content}
            {message.streaming && <span className="animate-pulse text-primary">▍</span>}
        </div>
    );
}

function RoleAvatar({ role }) {
    if (role === 'user') {
        return (
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
                <User className="h-3.5 w-3.5" />
            </span>
        );
    }

    if (role === 'assistant') {
        return (
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-foreground">
                <Bot className="h-3.5 w-3.5" />
            </span>
        );
    }

    return (
        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-600">
            <MessageSquare className="h-3.5 w-3.5" />
        </span>
    );
}

export default function MessageList({
    messages,
    mode = 'agent',
    viewMode = 'pretty',
    onToolApproval,
    approvalDisabled = false,
}) {
    if (!messages.length) {
        return (
            <div className="flex flex-col items-center justify-center px-6 py-20 text-center">
                <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                    <Sparkles className="h-7 w-7 text-primary" />
                </div>
                <p className="text-base font-medium">New chat</p>
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    Test your flow with a chat prompt.
                </p>
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-3xl space-y-5 py-6">
            {messages.map((message) => (
                <div key={message.id} className="flex gap-3">
                    <RoleAvatar role={message.role} />
                    <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold capitalize text-foreground">
                                {message.role === 'assistant' ? 'AI' : message.role}
                            </span>
                            {message.meta?.status === 'awaiting_input' && (
                                <Badge variant="outline" className="text-[10px]">
                                    Awaiting input
                                </Badge>
                            )}
                            {message.meta?.status === 'awaiting_tool_approval' && (
                                <Badge variant="outline" className="text-[10px]">
                                    Tool approval
                                </Badge>
                            )}
                            {message.meta?.status === 'completed' && (
                                <Badge variant="secondary" className="text-[10px]">
                                    Completed
                                </Badge>
                            )}
                            {message.meta?.usage && (
                                <>
                                    <span className="text-[10px] text-muted-foreground">
                                        {formatTokens(message.meta.usage.totalTokens)}
                                    </span>
                                    <span className="text-[10px] text-muted-foreground">
                                        {formatCost(message.meta.usage.estimatedCost, message.meta.usage.currency)}
                                    </span>
                                </>
                            )}
                            {message.meta?.status === 'running' && message.streaming && (
                                <Badge variant="outline" className="text-[10px]">
                                    Running
                                </Badge>
                            )}
                            {message.meta?.status === 'failed' && (
                                <Badge variant="destructive" className="text-[10px]">
                                    Failed
                                </Badge>
                            )}
                            {message.meta?.traceId && message.meta?.status === 'failed' && mode === 'workflow' && (
                                <button
                                    type="button"
                                    className="text-[10px] text-primary underline-offset-2 hover:underline"
                                    onClick={() =>
                                        window.dispatchEvent(
                                            new CustomEvent('workflow-view-trace', {
                                                detail: { traceId: message.meta.traceId },
                                            }),
                                        )
                                    }
                                >
                                    View trace #{message.meta.traceId}
                                </button>
                            )}
                        </div>

                        <div
                            className={cn(
                                'rounded-xl px-0 py-0',
                                message.role === 'system' && 'rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2',
                            )}
                        >
                            {message.role === 'assistant' && mode === 'workflow' ? (
                                <WorkflowAssistantContent message={message} viewMode={viewMode} />
                            ) : (
                                <div className="whitespace-pre-wrap text-sm leading-relaxed">
                                    {message.content}
                                    {message.streaming && <span className="animate-pulse text-primary">▍</span>}
                                </div>
                            )}
                        </div>

                        {message.attachments?.length > 0 && (
                            <div>
                                {message.attachments.map((attachment) => (
                                    <AttachmentPreview key={attachment.id} attachment={attachment} />
                                ))}
                            </div>
                        )}
                        {message.meta?.status === 'awaiting_tool_approval' && (
                            <ToolApprovalCard
                                message={message}
                                disabled={approvalDisabled}
                                onDecision={onToolApproval}
                            />
                        )}
                        {message.meta?.toolEvents?.map((tool, index) => (
                            <ToolEventBlock key={`${message.id}-tool-${index}`} tool={tool} />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
