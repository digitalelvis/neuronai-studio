import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { validateGraphWithLivewire, applyGraphImport } from './graphJson';

function extractGraph(parsed) {
    if (parsed?.graph && Array.isArray(parsed.graph.nodes)) {
        return parsed.graph;
    }

    if (Array.isArray(parsed?.nodes)) {
        return parsed;
    }

    return null;
}

export default function GraphJsonPanel({
    readOnly = false,
    onApply = applyGraphImport,
    onValidate = validateGraphWithLivewire,
}) {
    const [jsonText, setJsonText] = useState('{}');
    const [jsonError, setJsonError] = useState('');
    const [validationErrors, setValidationErrors] = useState([]);
    const [applying, setApplying] = useState(false);

    const refreshFromCanvas = useCallback(() => {
        const graph = window.__workflowGraphExport?.() ?? window.__workflowGraph;
        if (graph) {
            setJsonText(JSON.stringify(graph, null, 2));
            setJsonError('');
            setValidationErrors([]);
        }
    }, []);

    useEffect(() => {
        refreshFromCanvas();

        const onGraphChange = () => refreshFromCanvas();
        const onGraphLoaded = () => refreshFromCanvas();

        window.addEventListener('workflow-graph-changed', onGraphChange);
        window.addEventListener('workflow-canvas-loaded', onGraphLoaded);

        return () => {
            window.removeEventListener('workflow-graph-changed', onGraphChange);
            window.removeEventListener('workflow-canvas-loaded', onGraphLoaded);
        };
    }, [refreshFromCanvas]);

    const parseGraph = () => {
        try {
            const parsed = JSON.parse(jsonText);
            const graph = extractGraph(parsed);

            if (!graph) {
                setJsonError('JSON must contain a graph with a nodes array.');
                return null;
            }

            setJsonError('');
            return graph;
        } catch {
            setJsonError('Invalid JSON syntax.');
            return null;
        }
    };

    const handleApply = async () => {
        if (readOnly) {
            return;
        }

        const graph = parseGraph();
        if (!graph) {
            return;
        }

        setApplying(true);
        setValidationErrors([]);

        try {
            const result = await onValidate?.(graph);

            if (!result?.valid) {
                setValidationErrors(result?.errors ?? ['Graph validation failed.']);
                return;
            }

            await onApply?.(graph);
            setValidationErrors([]);
        } finally {
            setApplying(false);
        }
    };

    return (
        <div className="flex h-full flex-col gap-2 p-2">
            <div className="flex gap-2">
                <Button type="button" variant="outline" size="sm" onClick={refreshFromCanvas}>
                    Refresh
                </Button>
                {!readOnly && (
                    <Button type="button" size="sm" onClick={handleApply} disabled={applying}>
                        {applying ? 'Applying…' : 'Apply to canvas'}
                    </Button>
                )}
            </div>
            <Textarea
                className="min-h-0 flex-1 resize-none font-mono text-xs"
                value={jsonText}
                readOnly={readOnly}
                onChange={(event) => {
                    setJsonText(event.target.value);
                    setJsonError('');
                    setValidationErrors([]);
                }}
            />
            {jsonError && <p className="text-xs text-destructive">{jsonError}</p>}
            {validationErrors.length > 0 && (
                <ul className="space-y-1 text-xs text-destructive">
                    {validationErrors.map((error) => (
                        <li key={error}>{error}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export { extractGraph };
