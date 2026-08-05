import { useCallback, useEffect, useMemo, useState } from 'react';
import { Braces, Copy, Check, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { NodeTypeIcon } from '../nodes/nodeIcons';
import {
    bindVariableInspectEvents,
    formatInspectValue,
    getVariableInspectState,
    hydrateVariableInspectFromTraces,
    resetVariableInspectCache,
    subscribeVariableInspect,
} from './variable-inspect';

function selectionId(nodeId, key) {
    return `${nodeId}::${key}`;
}

export default function VariableInspectTab({
    workflowConfig = {},
    nodeTypesMeta = {},
    onActionsChange,
}) {
    const workflowId = workflowConfig.workflowId ?? null;
    const [cache, setCache] = useState(() => getVariableInspectState());
    const [selectedId, setSelectedId] = useState(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        return bindVariableInspectEvents();
    }, []);

    useEffect(() => {
        return subscribeVariableInspect(setCache);
    }, []);

    useEffect(() => {
        if (!workflowId || !workflowConfig.tracesIndexUrl || !workflowConfig.traceShowJsonUrlTemplate) {
            return;
        }

        let cancelled = false;

        (async () => {
            const next = await hydrateVariableInspectFromTraces({
                workflowId,
                tracesIndexUrl: workflowConfig.tracesIndexUrl,
                traceShowJsonUrlTemplate: workflowConfig.traceShowJsonUrlTemplate,
            });
            if (!cancelled) {
                setCache(next);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workflowId, workflowConfig.tracesIndexUrl, workflowConfig.traceShowJsonUrlTemplate]);

    const hasCache = cache.tree.length > 0;

    const selected = useMemo(() => {
        if (!selectedId) {
            return null;
        }

        for (const group of cache.tree) {
            for (const variable of group.variables) {
                if (selectionId(group.nodeId, variable.key) === selectedId) {
                    return { group, variable };
                }
            }
        }

        return null;
    }, [cache.tree, selectedId]);

    useEffect(() => {
        if (selectedId && !selected) {
            setSelectedId(null);
        }
    }, [selected, selectedId]);

    const handleReset = useCallback(() => {
        resetVariableInspectCache();
        setSelectedId(null);
    }, []);

    const handleCopy = useCallback(async () => {
        if (!selected) {
            return;
        }

        const text = formatInspectValue(selected.variable.value);
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard may be unavailable.
        }
    }, [selected]);

    useEffect(() => {
        if (!onActionsChange) {
            return;
        }

        onActionsChange(
            <>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                    onClick={handleReset}
                    disabled={!hasCache}
                >
                    <RotateCcw className="h-3 w-3" />
                    Redefinir tudo
                </Button>
                {selected && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        onClick={handleCopy}
                        title="Copy value"
                    >
                        {copied ? (
                            <Check className="h-3.5 w-3.5 text-green-500" />
                        ) : (
                            <Copy className="h-3.5 w-3.5" />
                        )}
                    </Button>
                )}
            </>,
        );

        return () => onActionsChange(null);
    }, [copied, handleCopy, handleReset, hasCache, onActionsChange, selected]);

    const focusNode = useCallback((nodeId) => {
        if (!nodeId || nodeId === 'start' || nodeId === '__start__') {
            return;
        }

        window.dispatchEvent(new CustomEvent('canvas-focus-node', { detail: { id: nodeId } }));
    }, []);

    return (
        <div className="ab-variable-inspect-body">
            <aside className="ab-variable-inspect-sidebar">
                <ScrollArea className="h-full">
                    {cache.loading && (
                        <p className="px-3 py-4 text-xs text-muted-foreground">
                            Loading cached variables…
                        </p>
                    )}
                    {!cache.loading && cache.error && (
                        <p className="px-3 py-4 text-xs text-destructive">{cache.error}</p>
                    )}
                    {!cache.loading && !cache.error && !hasCache && (
                        <p className="px-3 py-4 text-xs text-muted-foreground">
                            Run the playground to cache node variables.
                        </p>
                    )}
                    {!cache.loading &&
                        cache.tree.map((group) => {
                            const meta = nodeTypesMeta[group.nodeType] || {};
                            return (
                                <div key={group.nodeId} className="ab-variable-inspect-group">
                                    <button
                                        type="button"
                                        className="ab-variable-inspect-group-title"
                                        onClick={() => focusNode(group.nodeId)}
                                    >
                                        <NodeTypeIcon
                                            name={meta.icon || (group.nodeType === 'start' ? 'play' : 'circle')}
                                            className="h-3.5 w-3.5 shrink-0"
                                        />
                                        <span className="truncate">{group.label}</span>
                                    </button>
                                    <ul className="ab-variable-inspect-var-list">
                                        {group.variables.map((variable) => {
                                            const id = selectionId(group.nodeId, variable.key);
                                            const isActive = id === selectedId;
                                            return (
                                                <li key={id}>
                                                    <button
                                                        type="button"
                                                        className={`ab-variable-inspect-var ${isActive ? 'is-active' : ''}`}
                                                        onClick={() => setSelectedId(id)}
                                                    >
                                                        <Braces className="h-3 w-3 shrink-0 text-sky-400" />
                                                        <span className="min-w-0 flex-1 truncate font-medium">
                                                            {variable.key}
                                                        </span>
                                                        <span className="shrink-0 text-[10px] text-muted-foreground">
                                                            {variable.type}
                                                        </span>
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            );
                        })}
                </ScrollArea>
            </aside>

            <main className="ab-variable-inspect-detail">
                {selected ? (
                    <>
                        <div className="ab-variable-inspect-detail-header">
                            <span className="truncate text-sm text-muted-foreground">
                                {selected.group.label}
                                <span className="mx-1.5 text-border">/</span>
                                <span className="font-medium text-foreground">{selected.variable.key}</span>
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {selected.variable.type}
                            </span>
                        </div>
                        <ScrollArea className="min-h-0 flex-1">
                            <pre className="ab-variable-inspect-value">
                                {formatInspectValue(selected.variable.value)}
                            </pre>
                        </ScrollArea>
                    </>
                ) : (
                    <div className="ab-variable-inspect-empty">
                        <Braces className="h-10 w-10 text-sky-500/80" />
                        <h3 className="mt-3 text-sm font-semibold text-foreground">
                            Inspecionar variável
                        </h3>
                        <p className="mt-1.5 max-w-sm text-center text-xs leading-relaxed text-muted-foreground">
                            Após executar o workflow no Playground, selecione uma variável na lista para ver
                            o valor em cache deste nó.
                        </p>
                    </div>
                )}
            </main>
        </div>
    );
}
