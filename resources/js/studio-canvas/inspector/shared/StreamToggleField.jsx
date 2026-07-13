import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export default function StreamToggleField({
    stream = false,
    structured = false,
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
            {structured && (
                <p className="text-xs text-muted-foreground">
                    Streaming is skipped for structured output — the full response is required for validation.
                </p>
            )}
        </div>
    );
}
