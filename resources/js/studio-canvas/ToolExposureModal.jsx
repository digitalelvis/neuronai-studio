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
import { Textarea } from '@/components/ui/textarea';
import {
    defaultToolExposure,
    isValidToolExposureSlug,
    resolveToolExposureForSave,
} from './inspector/nodeUtils';

export default function ToolExposureModal({
    open,
    onOpenChange,
    nodeId,
    nodeData = {},
    typeMeta = {},
    readOnly = false,
    onSave,
}) {
    const seed = useMemo(() => defaultToolExposure(nodeData, typeMeta), [nodeData, typeMeta]);
    const [slug, setSlug] = useState(seed.slug);
    const [description, setDescription] = useState(seed.description);
    const [inputDescription, setInputDescription] = useState(
        seed.parameters?.input?.description || 'Task for the specialist',
    );
    const [error, setError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        const next = defaultToolExposure(nodeData, typeMeta);
        setSlug(next.slug);
        setDescription(next.description);
        setInputDescription(next.parameters?.input?.description || 'Task for the specialist');
        setError('');
    }, [open, nodeData, typeMeta]);

    const applySlugDefault = () => {
        if (slug.trim() !== '') {
            return;
        }

        const prefix = typeMeta?.tool_exposure?.slug_prefix || 'call_agent';
        setSlug(prefix);
    };

    const handleSave = () => {
        const draft = {
            ...nodeData,
            tool_exposure: {
                slug,
                description,
                parameters: {
                    input: {
                        controlled_by: 'caller',
                        description: inputDescription.trim() || 'Task for the specialist',
                    },
                },
            },
        };

        const exposure = resolveToolExposureForSave(draft, typeMeta);

        if (!isValidToolExposureSlug(exposure.slug)) {
            setError('Slug must be a valid function name (letters, numbers, underscore; start with a letter or _).');
            return;
        }

        setSlug(exposure.slug);
        setDescription(exposure.description);
        onSave?.({
            ...nodeData,
            tool_mode: true,
            tool_exposure: exposure,
        });
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Actions</DialogTitle>
                    <DialogDescription>
                        Configure how the supervisor calls this agent as a tool
                        {nodeId ? ` (${nodeId})` : ''}.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="tool-exposure-slug">Slug</Label>
                        <Input
                            id="tool-exposure-slug"
                            value={slug}
                            onChange={(e) => {
                                setSlug(e.target.value);
                                setError('');
                            }}
                            onBlur={applySlugDefault}
                            placeholder={typeMeta?.tool_exposure?.slug_prefix || 'call_agent'}
                            disabled={readOnly}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            Function name the supervisor model will call.
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="tool-exposure-description">Description</Label>
                        <Textarea
                            id="tool-exposure-description"
                            rows={4}
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="When should the supervisor use this agent?"
                            disabled={readOnly}
                        />
                    </div>

                    <div className="space-y-2 rounded-md border border-border p-3">
                        <Label className="text-xs uppercase tracking-wide text-muted-foreground">
                            Parameters
                        </Label>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="tool-exposure-input">input</Label>
                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                    controlled by caller
                                </span>
                            </div>
                            <Input
                                id="tool-exposure-input"
                                value={inputDescription}
                                onChange={(e) => setInputDescription(e.target.value)}
                                placeholder="Task for the specialist"
                                disabled={readOnly}
                            />
                            <p className="text-xs text-muted-foreground">
                                Primary input is always provided by the calling agent at runtime.
                            </p>
                        </div>
                    </div>

                    {error && <p className="text-sm text-destructive">{error}</p>}
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    {!readOnly && (
                        <Button onClick={handleSave}>Save</Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
