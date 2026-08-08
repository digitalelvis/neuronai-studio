import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    AlertCircle,
    ChevronDown,
    ChevronUp,
    ListTree,
    Terminal,
    Variable,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TraceList, TraceDetailSheet } from '../../studio-traces';
import {
    getVariableInspectState,
    loadInspectPanelPrefs,
    saveInspectPanelPrefs,
    subscribeVariableInspect,
} from './variable-inspect';
import VariableInspectTab from './VariableInspectTab';

const DEFAULT_HEIGHT = 320;
const MIN_HEIGHT = 200;
const MAX_HEIGHT = 560;

const TABS = [
    { id: 'variables', label: 'Variables', icon: Variable },
    { id: 'traces', label: 'Traces', icon: ListTree },
    { id: 'events', label: 'Events', icon: Terminal },
    { id: 'validation', label: 'Validation', icon: AlertCircle },
];

function countInspectVariables(tree = []) {
    if (!Array.isArray(tree)) {
        return 0;
    }

    return tree.reduce((total, group) => {
        const vars = Array.isArray(group?.variables) ? group.variables.length : 0;
        return total + vars;
    }, 0);
}

function formatTabLabel(base, count) {
    return count > 0 ? `${base} (${count})` : base;
}

export default function BottomDock({
    workflowConfig = {},
    nodeTypesMeta = {},
    validationMessage = '',
    validationErrorCount = 0,
}) {
    const workflowId = workflowConfig.workflowId ?? null;
    const prefs = useMemo(() => loadInspectPanelPrefs(workflowId), [workflowId]);

    const [open, setOpen] = useState(() => Boolean(prefs.open));
    const [height, setHeight] = useState(() =>
        typeof prefs.height === 'number' ? prefs.height : DEFAULT_HEIGHT,
    );
    const [tab, setTab] = useState(() => {
        const preferred = prefs.tab;
        return TABS.some((item) => item.id === preferred) ? preferred : 'variables';
    });
    const [tabActions, setTabActions] = useState(null);
    const [selectedTraceId, setSelectedTraceId] = useState(null);
    const [traceSheetOpen, setTraceSheetOpen] = useState(false);
    const [refreshToken, setRefreshToken] = useState(0);
    const [runEvents, setRunEvents] = useState([]);
    const [variableCache, setVariableCache] = useState(() => getVariableInspectState());
    const [tracesTotal, setTracesTotal] = useState(0);

    useEffect(() => {
        if (!workflowId) {
            return;
        }

        saveInspectPanelPrefs(workflowId, { open, height, tab });
    }, [workflowId, open, height, tab]);

    useEffect(() => subscribeVariableInspect(setVariableCache), []);

    const openTab = useCallback((nextTab, { expand = true } = {}) => {
        setTab(nextTab);
        if (expand) {
            setOpen(true);
        }
    }, []);

    useEffect(() => {
        const onOpenTraces = () => openTab('traces');
        window.addEventListener('workflow-open-traces', onOpenTraces);
        return () => window.removeEventListener('workflow-open-traces', onOpenTraces);
    }, [openTab]);

    useEffect(() => {
        if (validationMessage) {
            openTab('validation');
        }
    }, [openTab, validationMessage]);

    useEffect(() => {
        const onRunStart = () => {
            setRunEvents([{ id: Date.now(), text: 'Run started', level: 'info' }]);
        };

        const onExecution = (event) => {
            const detail = event.detail || {};
            const text =
                detail.event === 'step_started'
                    ? `Started ${detail.node_id || 'node'}`
                    : detail.event === 'step_completed'
                      ? `Completed ${detail.node_id || 'node'}`
                      : detail.event === 'trace_failed'
                        ? 'Run failed'
                        : detail.event === 'trace_completed'
                          ? 'Run completed'
                          : detail.event || 'Event';

            if (detail.event === 'trace_failed') {
                const errorMessage =
                    detail.message || detail.error || 'Run failed';
                window.NeuronAIStudioToast?.error(errorMessage);
            }

            setRunEvents((current) => [
                ...current.slice(-99),
                {
                    id: `${Date.now()}-${current.length}`,
                    text,
                    level: detail.event === 'trace_failed' ? 'error' : 'info',
                    nodeId: detail.node_id,
                },
            ]);
        };

        const onFinished = () => setRefreshToken((n) => n + 1);

        window.addEventListener('canvas-run-start', onRunStart);
        window.addEventListener('canvas-trace-start', onRunStart);
        window.addEventListener('canvas-execution-event', onExecution);
        window.addEventListener('workflow-trace-finished', onFinished);

        return () => {
            window.removeEventListener('canvas-run-start', onRunStart);
            window.removeEventListener('canvas-trace-start', onRunStart);
            window.removeEventListener('canvas-execution-event', onExecution);
            window.removeEventListener('workflow-trace-finished', onFinished);
        };
    }, []);

    const focusNode = useCallback((nodeId) => {
        if (!nodeId) {
            return;
        }

        window.dispatchEvent(new CustomEvent('canvas-focus-node', { detail: { id: nodeId } }));
    }, []);

    const handleTabClick = useCallback(
        (nextTab) => {
            if (tab === nextTab && open) {
                setOpen(false);
                return;
            }

            openTab(nextTab);
        },
        [open, openTab, tab],
    );

    const toggleOpen = useCallback(() => {
        setOpen((value) => !value);
    }, []);

    const onResizePointerDown = useCallback(
        (event) => {
            event.preventDefault();
            const startY = event.clientY;
            const startHeight = height;

            const onMove = (moveEvent) => {
                const delta = startY - moveEvent.clientY;
                const next = Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, startHeight + delta));
                setHeight(next);
            };

            const onUp = () => {
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
        },
        [height],
    );

    const handleTracesMetaChange = useCallback((meta = {}) => {
        const total = Number(meta.total);
        setTracesTotal(Number.isFinite(total) && total > 0 ? total : 0);
    }, []);

    const tabCounts = useMemo(
        () => ({
            variables: countInspectVariables(variableCache?.tree),
            traces: tracesTotal,
            events: runEvents.length,
            validation: validationErrorCount > 0 ? validationErrorCount : 0,
        }),
        [runEvents.length, tracesTotal, validationErrorCount, variableCache?.tree],
    );

    const activeTabMeta = TABS.find((item) => item.id === tab) || TABS[0];
    const ActiveIcon = activeTabMeta.icon;
    const activeTabLabel = formatTabLabel(activeTabMeta.label, tabCounts[activeTabMeta.id] ?? 0);

    return (
        <div className={`ab-bottom-dock ${open ? 'ab-bottom-dock--open' : ''}`}>
            <div
                className={`ab-bottom-dock-panel ${open ? '' : 'ab-bottom-dock-panel--collapsed'}`}
                style={open ? { height } : undefined}
                aria-hidden={!open}
            >
                <div
                    className="ab-bottom-dock-resize"
                    onPointerDown={onResizePointerDown}
                    role="separator"
                    aria-orientation="horizontal"
                    aria-label="Resize bottom panel"
                />

                <div className="ab-bottom-dock-toolbar">
                    <div className="flex min-w-0 items-center gap-2">
                        <ActiveIcon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <h2 className="truncate text-xs font-semibold uppercase tracking-wide text-foreground">
                            {activeTabLabel}
                        </h2>
                    </div>
                    <div className="flex items-center gap-1">{tab === 'variables' ? tabActions : null}</div>
                </div>

                <div className="ab-bottom-dock-content">
                    <div className={tab === 'variables' ? 'flex h-full min-h-0 flex-1 flex-col' : 'hidden'}>
                        <VariableInspectTab
                            workflowConfig={workflowConfig}
                            nodeTypesMeta={nodeTypesMeta}
                            onActionsChange={setTabActions}
                        />
                    </div>

                    <div className={tab === 'traces' ? 'h-full min-h-0 overflow-hidden' : 'hidden'}>
                        <TraceList
                            tracesIndexUrl={workflowConfig.tracesIndexUrl}
                            selectedTraceId={selectedTraceId}
                            onSelectTrace={(trace) => {
                                setSelectedTraceId(trace.id);
                                setTraceSheetOpen(true);
                            }}
                            refreshToken={refreshToken}
                            onTracesMetaChange={handleTracesMetaChange}
                        />
                    </div>

                    {tab === 'events' && (
                        <div className="h-full overflow-auto p-3">
                            {runEvents.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    Run the playground to see live events.
                                </p>
                            ) : (
                                <ul className="space-y-1.5">
                                    {runEvents.map((event) => (
                                        <li key={event.id}>
                                            <button
                                                type="button"
                                                className={`w-full rounded-md px-2 py-1.5 text-left text-xs ${event.level === 'error' ? 'bg-destructive/10 text-destructive' : 'bg-muted/40 text-foreground'} ${event.nodeId ? 'hover:bg-muted/70' : ''}`}
                                                onClick={() => focusNode(event.nodeId)}
                                                disabled={!event.nodeId}
                                            >
                                                {event.text}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {tab === 'validation' && (
                        <div className="h-full overflow-auto p-3">
                            {validationMessage ? (
                                <p className="whitespace-pre-wrap text-xs text-muted-foreground">
                                    {validationMessage}
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    No validation messages. Use Validate in the header.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <div className="ab-bottom-dock-bar">
                <div className="ab-bottom-dock-tabs" role="tablist" aria-label="Bottom panel tabs">
                    {TABS.map((item) => {
                        const Icon = item.icon;
                        const active = tab === item.id && open;
                        const count = tabCounts[item.id] ?? 0;
                        return (
                            <button
                                key={item.id}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                className={`ab-bottom-dock-tab ${active ? 'is-active' : ''}`}
                                onClick={() => handleTabClick(item.id)}
                            >
                                <Icon className="h-3.5 w-3.5 shrink-0" />
                                <span>{formatTabLabel(item.label, count)}</span>
                            </button>
                        );
                    })}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 shrink-0"
                    onClick={toggleOpen}
                    title={open ? 'Collapse panel' : 'Expand panel'}
                >
                    {open ? (
                        <ChevronDown className="h-3.5 w-3.5" />
                    ) : (
                        <ChevronUp className="h-3.5 w-3.5" />
                    )}
                </Button>
            </div>

            <TraceDetailSheet
                open={traceSheetOpen}
                onOpenChange={setTraceSheetOpen}
                traceId={selectedTraceId}
                traceShowJsonUrlTemplate={workflowConfig.traceShowJsonUrlTemplate}
                traceShowUrlTemplate={workflowConfig.traceShowUrlTemplate}
            />
        </div>
    );
}
