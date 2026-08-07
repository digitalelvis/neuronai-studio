import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export default function ToolModeToggle({ nodeId, toolMode, readOnly = false, onCheckedChange }) {
    return (
        <div className="flex items-center justify-between gap-2 rounded-md border border-border px-2 py-1.5">
            <div className="min-w-0">
                <Label htmlFor={`tool-mode-${nodeId}`} className="text-[11px]">
                    Tool Mode
                </Label>
                <p className="text-[10px] text-muted-foreground leading-tight">
                    {toolMode
                        ? 'Acts as a toolset for a supervisor agent.'
                        : 'Acts as a workflow step.'}
                </p>
            </div>
            <Switch
                id={`tool-mode-${nodeId}`}
                checked={toolMode}
                onCheckedChange={(checked) => onCheckedChange?.(Boolean(checked))}
                disabled={readOnly}
            />
        </div>
    );
}
