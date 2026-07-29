import { useEffect, useState } from 'react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function WorkflowMetaDialog({
    open,
    onOpenChange,
    name = '',
    description = '',
    status = 'draft',
    readOnly = false,
    onSave,
}) {
    const [draftName, setDraftName] = useState(name);
    const [draftDescription, setDraftDescription] = useState(description);
    const [draftStatus, setDraftStatus] = useState(status);

    useEffect(() => {
        if (!open) {
            return;
        }

        setDraftName(name);
        setDraftDescription(description);
        setDraftStatus(status);
    }, [open, name, description, status]);

    const handleSave = () => {
        const trimmed = draftName.trim();
        if (!trimmed) {
            return;
        }

        onSave?.({
            name: trimmed,
            description: draftDescription,
            status: draftStatus,
        });
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit workflow</DialogTitle>
                    <DialogDescription>
                        Update the workflow name, description, and status.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="workflow-meta-name">Name</Label>
                        <Input
                            id="workflow-meta-name"
                            value={draftName}
                            onChange={(e) => setDraftName(e.target.value)}
                            placeholder="Workflow name"
                            disabled={readOnly}
                            autoFocus
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="workflow-meta-description">Description</Label>
                        <Textarea
                            id="workflow-meta-description"
                            className="min-h-22 resize-none"
                            value={draftDescription}
                            onChange={(e) => setDraftDescription(e.target.value)}
                            placeholder="Description"
                            disabled={readOnly}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="workflow-meta-status">Status</Label>
                        <Select
                            value={draftStatus}
                            onValueChange={setDraftStatus}
                            disabled={readOnly}
                        >
                            <SelectTrigger id="workflow-meta-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">Published</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    {!readOnly && (
                        <Button onClick={handleSave} disabled={!draftName.trim()}>
                            Save
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
