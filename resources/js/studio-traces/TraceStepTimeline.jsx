import { cn } from '@/lib/utils';
import { formatDuration } from './TraceListItem';
import { formatTokens } from '@/lib/formatUsage';

export default function TraceStepTimeline({ steps = [], selectedStepId, onSelectStep }) {
    return (
        <div className="flex h-full flex-col border-r border-border">
            <div className="border-b border-border px-4 py-2 text-xs font-medium uppercase text-muted-foreground">Timeline</div>
            <div className="flex-1 overflow-auto">
                <div className="space-y-1 p-2">
                    {steps.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">No steps recorded.</p>
                    ) : (
                        steps.map((step) => (
                            <button
                                key={step.id}
                                type="button"
                                onClick={() => onSelectStep?.(step.id)}
                                className={cn(
                                    'flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/50',
                                    selectedStepId === step.id && 'bg-muted',
                                )}
                            >
                                <span>
                                    <span className="font-medium">{step.node_type}</span>
                                    <span className="ml-2 text-xs text-muted-foreground">{step.node_id}</span>
                                </span>
                                <span className="flex flex-col items-end text-xs text-muted-foreground">
                                    {step.duration_ms != null && <span>{formatDuration(step.duration_ms)}</span>}
                                    {step.node_type === 'llm' && <span>{formatTokens(step.total_tokens)}</span>}
                                </span>
                            </button>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
