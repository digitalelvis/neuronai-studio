import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DelayNodeFields({
    data,
    readOnly = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <div className="space-y-2">
            <Label>Seconds</Label>
            <Input
                type="number"
                min={0}
                value={data.seconds ?? data.delay ?? 1}
                onChange={(e) => updateField('seconds', Number(e.target.value))}
                disabled={readOnly}
            />
        </div>
    );
}
