import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export default function VisionToggleField({
    vision = false,
    readOnly = false,
    onChange,
    defaultOn = false,
}) {
    const checked = vision === true || (vision !== false && defaultOn);

    return (
        <div className="space-y-2 rounded-md border border-border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div className="space-y-0.5">
                    <Label htmlFor="vision-toggle">Vision</Label>
                    <p className="text-xs text-muted-foreground">
                        Include run attachments (images, files) in the model message.
                    </p>
                </div>
                <Checkbox
                    id="vision-toggle"
                    checked={Boolean(checked)}
                    onCheckedChange={(value) => onChange?.({ vision: value === true })}
                    disabled={readOnly}
                />
            </div>
        </div>
    );
}
