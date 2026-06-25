import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { importGraphFromText } from './graphJson';

export default function ImportJsonDialog({ open, onOpenChange }) {
    const [text, setText] = useState('');
    const [error, setError] = useState('');
    const [importing, setImporting] = useState(false);

    const handleImport = async () => {
        setError('');
        setImporting(true);

        try {
            const result = await importGraphFromText(text);

            if (result.cancelled) {
                return;
            }

            if (!result.ok) {
                setError((result.errors ?? ['Import failed.']).join(' '));
                return;
            }

            setText('');
            onOpenChange(false);
        } finally {
            setImporting(false);
        }
    };

    const handleFile = async (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        setText(await file.text());
        event.target.value = '';
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Import workflow JSON</DialogTitle>
                    <DialogDescription>
                        Paste graph JSON or upload a .json file. Metadata envelope {'{ meta, graph }'} is supported.
                    </DialogDescription>
                </DialogHeader>
                <Textarea
                    className="min-h-[240px] font-mono text-xs"
                    value={text}
                    onChange={(e) => {
                        setText(e.target.value);
                        setError('');
                    }}
                    placeholder='{"nodes": [...], "edges": [...]}'
                />
                {error && <p className="text-sm text-destructive">{error}</p>}
                <DialogFooter className="gap-2 sm:justify-between">
                    <label className="cursor-pointer">
                        <Button type="button" variant="outline" size="sm" asChild>
                            <span>Upload file</span>
                        </Button>
                        <input type="file" accept=".json,application/json" hidden onChange={handleFile} />
                    </label>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleImport} disabled={importing || !text.trim()}>
                            {importing ? 'Importing…' : 'Import'}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
