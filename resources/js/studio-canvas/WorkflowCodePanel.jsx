import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { exportWorkflowWithLivewire, previewWorkflowCodeWithLivewire } from './workflowCode';

export default function WorkflowCodePanel({ readOnly = false }) {
    const [code, setCode] = useState('');
    const [classLabel, setClassLabel] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [copied, setCopied] = useState(false);
    const requestIdRef = useRef(0);

    const refreshFromCanvas = useCallback(async () => {
        const requestId = ++requestIdRef.current;
        setLoading(true);
        setError('');

        const result = await previewWorkflowCodeWithLivewire();

        if (requestId !== requestIdRef.current) {
            return;
        }

        setLoading(false);

        if (!result.ok) {
            setError(result.error ?? 'Failed to generate code preview.');
            return;
        }

        setCode(result.code ?? '');
        setClassLabel(`${result.namespace}\\${result.className}`);
    }, []);

    useEffect(() => {
        refreshFromCanvas();

        const onRefresh = () => refreshFromCanvas();

        window.addEventListener('workflow-graph-changed', onRefresh);
        window.addEventListener('workflow-canvas-loaded', onRefresh);
        window.addEventListener('workflow-meta-changed', onRefresh);

        return () => {
            window.removeEventListener('workflow-graph-changed', onRefresh);
            window.removeEventListener('workflow-canvas-loaded', onRefresh);
            window.removeEventListener('workflow-meta-changed', onRefresh);
        };
    }, [refreshFromCanvas]);

    const handleCopy = async () => {
        if (!code) {
            return;
        }

        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setError('Could not copy to clipboard.');
        }
    };

    const handleExport = async () => {
        setExporting(true);
        setError('');

        const result = await exportWorkflowWithLivewire();

        setExporting(false);

        if (!result.ok) {
            setError(result.error ?? 'Export failed.');
        }
    };

    return (
        <div className="flex h-full flex-col gap-2 p-2">
            <div className="flex flex-wrap items-center gap-2">
                {classLabel && (
                    <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-muted-foreground">
                        {classLabel}
                    </span>
                )}
                <Button type="button" variant="outline" size="sm" onClick={refreshFromCanvas} disabled={loading}>
                    {loading ? 'Refreshing…' : 'Refresh'}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={handleCopy} disabled={!code}>
                    {copied ? 'Copied' : 'Copy'}
                </Button>
                {!readOnly && (
                    <Button type="button" size="sm" onClick={handleExport} disabled={exporting}>
                        {exporting ? 'Exporting…' : 'Export PHP'}
                    </Button>
                )}
            </div>
            <Textarea
                className="min-h-0 flex-1 resize-none font-mono text-xs"
                value={loading && !code ? 'Generating preview…' : code}
                readOnly
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
