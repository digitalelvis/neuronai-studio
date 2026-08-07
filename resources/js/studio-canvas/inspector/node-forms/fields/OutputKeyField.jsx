import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function OutputKeyField({
    value,
    defaultValue = '',
    onChange,
    readOnly = false,
    compact = false,
    hint = null,
}) {
    return (
        <div className="space-y-2">
            <Label>Output Key</Label>
            <Input
                value={value ?? defaultValue}
                onChange={(e) => onChange?.(e.target.value)}
                disabled={readOnly}
            />
            {!compact && hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
