import { useEffect, useMemo, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ExpandableTextField } from '@/components/ui/expandable-text-field';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

function cloneActions(actions = []) {
    return actions.map((action) => ({
        name: action.name || '',
        description: action.description || '',
        properties: (action.properties || []).map((property) => ({
            name: property.name || '',
            type: property.type || 'string',
            description: property.description || '',
            required: Boolean(property.required),
        })),
    }));
}

export default function ToolActionsModal({
    open,
    onOpenChange,
    nodeId,
    toolRef = '',
    toolMeta = null,
    readOnly = false,
    wireId = null,
    onSaved,
}) {
    const editable = Boolean(toolMeta?.editable) && !readOnly;
    const [drafts, setDrafts] = useState(() => cloneActions(toolMeta?.actions));
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const next = cloneActions(toolMeta?.actions);
        setDrafts(next);
        setSelectedIndex(0);
        setError('');
        setSaving(false);
    }, [open, toolMeta]);

    const selected = drafts[selectedIndex] || null;
    const isToolkit = drafts.length > 1;

    const title = useMemo(() => {
        if (toolMeta?.label) {
            return toolMeta.label;
        }

        return toolRef || 'Tool';
    }, [toolMeta, toolRef]);

    const updateSelected = (patch) => {
        setDrafts((current) =>
            current.map((action, index) => (index === selectedIndex ? { ...action, ...patch } : action)),
        );
    };

    const updateProperty = (propertyIndex, patch) => {
        setDrafts((current) =>
            current.map((action, index) => {
                if (index !== selectedIndex) {
                    return action;
                }

                return {
                    ...action,
                    properties: action.properties.map((property, i) =>
                        i === propertyIndex ? { ...property, ...patch } : property,
                    ),
                };
            }),
        );
    };

    const handleSave = async () => {
        if (!editable || !selected || !toolRef.startsWith('tool:db:')) {
            onOpenChange(false);
            return;
        }

        const toolId = Number(toolRef.replace('tool:db:', ''));

        if (!Number.isFinite(toolId) || toolId <= 0) {
            setError('Invalid tool reference.');
            return;
        }

        if (!wireId || !window.Livewire) {
            setError('Unable to save: Livewire component not available.');
            return;
        }

        setSaving(true);
        setError('');

        try {
            const component = window.Livewire.find(wireId);
            const result = await component.call('updateToolDefinition', toolId, selected);

            if (!result?.ok) {
                setError(result?.error || 'Failed to save tool definition.');
                setSaving(false);
                return;
            }

            onSaved?.(result.tool);
            onOpenChange(false);
        } catch (err) {
            setError(err?.message || 'Failed to save tool definition.');
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Actions</DialogTitle>
                    <DialogDescription>
                        ToolInterface schema for {title}
                        {nodeId ? ` (${nodeId})` : ''}.
                        {!editable ? ' This tool cannot be edited from the canvas.' : ''}
                    </DialogDescription>
                </DialogHeader>

                {!toolRef && (
                    <p className="text-sm text-muted-foreground">Select a tool first to inspect its actions.</p>
                )}

                {toolRef && drafts.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No action schema available for this tool
                        {toolMeta?.description ? `: ${toolMeta.description}` : '.'}
                    </p>
                )}

                {drafts.length > 0 && (
                    <div className="space-y-4">
                        {isToolkit && (
                            <div className="space-y-2">
                                <Label>Action</Label>
                                <Select
                                    value={String(selectedIndex)}
                                    onValueChange={(value) => setSelectedIndex(Number(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {drafts.map((action, index) => (
                                            <SelectItem key={`${action.name}-${index}`} value={String(index)}>
                                                {action.name || `Action ${index + 1}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {selected && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="tool-action-name">Slug / Name</Label>
                                    <Input
                                        id="tool-action-name"
                                        value={selected.name}
                                        onChange={(e) => updateSelected({ name: e.target.value })}
                                        disabled={!editable || isToolkit}
                                        autoComplete="off"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Function name the model will call (`ToolInterface::getName()`).
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tool-action-description">Description</Label>
                                    <ExpandableTextField
                                        id="tool-action-description"
                                        rows={3}
                                        value={selected.description}
                                        onChange={(e) => updateSelected({ description: e.target.value })}
                                        disabled={!editable || isToolkit}
                                        label="Edit text content"
                                    />
                                </div>

                                <div className="space-y-2 rounded-md border border-border p-3">
                                    <Label className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Parameters
                                    </Label>
                                    {selected.properties.length === 0 && (
                                        <p className="text-xs text-muted-foreground">No properties defined.</p>
                                    )}
                                    <div className="space-y-3">
                                        {selected.properties.map((property, propertyIndex) => (
                                            <div
                                                key={`${property.name}-${propertyIndex}`}
                                                className="space-y-2 rounded-md border border-border/60 p-2"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <Input
                                                        value={property.name}
                                                        onChange={(e) =>
                                                            updateProperty(propertyIndex, { name: e.target.value })
                                                        }
                                                        disabled={!editable || isToolkit}
                                                        className="h-8 font-mono text-xs"
                                                    />
                                                    <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                                        controlled by caller
                                                    </span>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Select
                                                        value={property.type || 'string'}
                                                        onValueChange={(value) =>
                                                            updateProperty(propertyIndex, { type: value })
                                                        }
                                                        disabled={!editable || isToolkit}
                                                    >
                                                        <SelectTrigger className="h-8">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="string">string</SelectItem>
                                                            <SelectItem value="number">number</SelectItem>
                                                            <SelectItem value="integer">integer</SelectItem>
                                                            <SelectItem value="boolean">boolean</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Checkbox
                                                            checked={property.required}
                                                            onCheckedChange={(checked) =>
                                                                updateProperty(propertyIndex, {
                                                                    required: Boolean(checked),
                                                                })
                                                            }
                                                            disabled={!editable || isToolkit}
                                                        />
                                                        Required
                                                    </label>
                                                </div>
                                                <Input
                                                    value={property.description}
                                                    onChange={(e) =>
                                                        updateProperty(propertyIndex, {
                                                            description: e.target.value,
                                                        })
                                                    }
                                                    placeholder="Property description"
                                                    disabled={!editable || isToolkit}
                                                    className="h-8 text-xs"
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}

                        {error && <p className="text-sm text-destructive">{error}</p>}
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                        {editable ? 'Cancel' : 'Close'}
                    </Button>
                    {editable && !isToolkit && (
                        <Button onClick={handleSave} disabled={saving || !selected}>
                            {saving ? 'Saving…' : 'Save'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
