import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * Toggle streaming tokens for agent/llm nodes.
 * When stream is on, `publish_reply` controls whether tokens reach the
 * external channel (WhatsApp / Vercel / AG-UI). Internal Studio SSE still
 * receives tokens for the timeline.
 */
export default function StreamToggleField({
    stream = false,
    structured = false,
    publishReply = true,
    readOnly = false,
    onChange,
}) {
    return (
        <div className="space-y-2 rounded-md border border-border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div className="space-y-0.5">
                    <Label htmlFor="stream-toggle">Stream tokens</Label>
                    <p className="text-xs text-muted-foreground">
                        Emit the response token-by-token during the step.
                    </p>
                </div>
                <Checkbox
                    id="stream-toggle"
                    checked={Boolean(stream)}
                    onCheckedChange={(checked) => onChange?.({ stream: checked === true })}
                    disabled={readOnly || structured}
                />
            </div>
            {stream && !structured && (
                <div className="flex items-center justify-between gap-3">
                    <div className="space-y-0.5">
                        <Label htmlFor="publish-reply-toggle">Publish to channel</Label>
                        <p className="text-xs text-muted-foreground">
                            Off = fill state only (internal). On = stream to WhatsApp / integrate.
                        </p>
                    </div>
                    <Checkbox
                        id="publish-reply-toggle"
                        checked={publishReply !== false}
                        onCheckedChange={(checked) =>
                            onChange?.({ publish_reply: checked === true })
                        }
                        disabled={readOnly}
                    />
                </div>
            )}
            {structured && (
                <p className="text-xs text-muted-foreground">
                    Streaming is skipped for structured output — the full response is required for validation.
                </p>
            )}
        </div>
    );
}
